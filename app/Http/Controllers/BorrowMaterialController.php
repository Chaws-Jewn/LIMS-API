<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use App\Models\BorrowMaterial;
use App\Models\Reservation;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Patron;
use App\Models\Book;
use Exception, Carbon, Storage;

/*  
    0 => already borrowed?
    1=> available
    2 => missing
    3 => unreturned
    4 => unlabeled
*/
    

class BorrowMaterialController extends Controller
{
    const URL = 'http://192.168.68.124:8000';
    public function borrowbook(Request $request)
    {
        $payload = json_decode($request->payload);
    
        // Check if the book_id exists in the books table
        $book = Book::find($payload->book_id);
        if (!$book) {
            return response()->json(['error' => 'Book not found'], 404);
        }
    
        // Check if the book is available
        if ($book->available == 0) {
            return response()->json(['error' => 'Book is not available for borrowing'], 400);
        }
    
        //user and patron information
        $user = User::find($payload->user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $patron = Patron::find($user->patron_id);
        if (!$patron) {
            return response()->json(['error' => 'Patron not found'], 404);
        }
        
        //number of materials allowed for this patron
        $materialsAllowed = $patron->materials_allowed;
        
        // allowed number of active borrows
        $activeBorrowsCount = BorrowMaterial::where('user_id', $payload->user_id)
                                            ->where('status', 1) // Assuming status 1 means active
                                            ->count();
        
        if ($activeBorrowsCount >= $materialsAllowed) {
            return response()->json(['error' => 'User already has the maximum number of active borrows allowed'], 400);
        }
    
        // Use a transaction to ensure both operations happen at the same time
        DB::beginTransaction();
    
        try {
            // Create a new BorrowMaterial instance
            $borrowMaterial = new BorrowMaterial();
            $borrowMaterial->book_id = $payload->book_id;
            $borrowMaterial->user_id = $payload->user_id;
            $borrowMaterial->fine = $payload->fine;
            $borrowMaterial->borrow_expiration = $payload->borrow_expiration;
            $borrowMaterial->borrow_date = $payload->borrow_date;
            $borrowMaterial->status = 1; // Assuming status 1 means active
            $borrowMaterial->save();
    
            // Set the book as not available
            $book->available = 0;
            $book->save();
    
            // Commit the transaction
            DB::commit();
            
            $data = ['borrow_material' => $borrowMaterial];
            return response()->json($data);
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            DB::rollBack();
            return response()->json(['error' => 'An error occurred while borrowing the book', 'details' => $e->getMessage()], 500);
        }
    }

    public function fromreservation(Request $request, $id)
    {
        $payload = json_decode($request->payload);
        
        // Check if the book_id exists in the books table
        $book = Book::find($payload->book_id);
        if (!$book) {
            return response()->json(['error' => 'Book not found'], 404);
        }
    
        // Check if the book is available
        if ($book->available == 0) {
            return response()->json(['error' => 'Book is not available for borrowing'], 400);
        }
    
        $reservation = Reservation::find($id);
        
        // Find the user
        $user = User::find($payload->user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
    
        // Get the patron ID associated with the user
        $patronId = $user->patron_id;
    
        // Retrieve the patron from the patrons table
        $patron = Patron::find($patronId);
        if (!$patron) {
            return response()->json(['error' => 'Patron not found for the user'], 404);
        }
    
        // Get the fine associated with the patron
        $fine = $patron->fine;
        if (!$fine) {
           
            $fine = 0; 
            
        }
    
        // Create a new BorrowMaterial instance
        $borrowMaterial = new BorrowMaterial();
        $borrowMaterial->user_id = $payload->user_id;
        $borrowMaterial->book_id = $payload->book_id;
        $borrowMaterial->fine = $fine; 
        $borrowMaterial->borrow_date = now(); 
        $borrowMaterial->borrow_expiration = now()->addWeek(); 
    
        $reservation->status = 0;
        $book->available = 0;
        $reservation->save();
        
        $borrowMaterial->save();
        $book->save();
    
        $data = ['borrow_material' => $borrowMaterial];
        return response()->json($data);
    }
    


    public function borrowlist(Request $request){
        $borrowMaterial = BorrowMaterial::with('user.program.department', 'user.patron')
                            ->whereHas('user', function($query) {
                                $query->where('status', 1);
                            })
                            ->get();
        return response()->json($borrowMaterial); 
    }

    public function returnedlist(Request $request){
        $borrowMaterial = BorrowMaterial::with(['user.program', 'user.department', 'user.patron'])
                            ->whereHas('user', function($query){
                                $query->where('status', 0);
                            })
                            ->get();
        return response()->json($borrowMaterial);
    }

    public function returnedlistid($id)
    {
        $returnedItems = BorrowMaterial::where('user_id', $id)
                                       ->get();
        if ($returnedItems->isEmpty()) {
            return response()->json(['message' => 'No returned items found for this user'], 404);
        }
        return response()->json($returnedItems, 200);
    }

    public function borrowEdit(Request $request){
        $payload = json_decode($request->payload);
  
        $borrowMaterial = BorrowMaterial::find($payload->id);
    
        if (!$borrowMaterial) {
            return response()->json(['error' => 'Borrow material not found'], 404);
        }

        $borrowMaterial->book_id = $payload->book_id;
        $borrowMaterial->user_id = $payload->user_id;
        $borrowMaterial->fine = $payload->fine;
        $borrowMaterial->borrow_expiration = $payload->borrow_expiration;
        $borrowMaterial->borrow_date = $payload->borrow_date;

        // Save the updated record
        $borrowMaterial->save();

        // Return the updated record as a response
        $data = ['borrow_material' => $borrowMaterial];
        return response()->json($data);
    }   

    public function userlist(Request $request){
        $users = User::with('program.department', 'patron')->where('role', '["user"]')->get();
        return response()->json($users, 200);
    }

    public function borrowcount(Request $request, $id){
        $user = User::find($id);
        $activeBorrowsCount = BorrowMaterial::where('user_id', $id)
                                    ->where('status', 1)
                                    ->count();
        return response()->json(['active_borrows_count' => $activeBorrowsCount]);
    }

    //return book

    public function returnbook(Request $request, $id)
    {
        // Start a database transaction
        DB::beginTransaction();
    
        try {
            // Find the BorrowMaterial by its ID
            $borrowMaterial = BorrowMaterial::find($id);
            
            // Check if the borrowed material exists
            if (!$borrowMaterial) {
                // Rollback the transaction if the borrowed material is not found
                DB::rollback();
                return response()->json(['message' => 'Borrowed material not found'], 404);
            }
    
            // Find the Book by the book_id associated with the BorrowMaterial
            $book = Book::find($borrowMaterial->book_id);
            
            // Set book availability to 1 (available)
            $book->available = 1;
    
            // Save the changes to the Book
            $book->save();
    
            // Update BorrowMaterial status and return date
            $borrowMaterial->status = 0;
            $borrowMaterial->date_returned = now();
            
            // Save the changes to the BorrowMaterial
            $borrowMaterial->save();
    
            // Commit the transaction if all operations succeed
            DB::commit();
    
            // Return a success response
            return response()->json(['message' => 'Book returned successfully'], 200);
        } catch (Exception $e) {
            // Rollback the transaction if any operation fails
            DB::rollback();
    
            // Handle the exception, e.g., log the error or return an error response
            return response()->json(['message' => 'An error occurred while returning the book'], 500);
        }
    }

    public function destroy($id)
    {
        // Find the record
        $borrowMaterial = BorrowMaterial::find($id);
       
        if (!$borrowMaterial) {
            return response()->json(['error' => 'BorrowMaterial not found'], 404);
        }

        $borrowMaterial->delete();
        return response()->json(['message' => 'BorrowMaterial deleted successfully']);
    }
    

    public function bookBorrowersReport(Request $request){
        // Fetch distinct borrowers with their user and program relations, including nested department
        $borrowers = BorrowMaterial::with(['user.program.department'])
            ->select('user_id')
            ->distinct()
            ->get();
    
        // Initialize arrays to store counts
        $borrowersByDepartment = [];
        $borrowersByGender = [
            'Male' => 0,
            'Female' => 0
        ];
    
        // Process each borrower to populate the counts
        foreach ($borrowers as $borrow) {
            $user = $borrow->user;
            $program = $user->program;
    
            // Increment count by department
            if ($program && $program->department) {
                $department = $program->department->full_department; // Assuming you want the full_department name
                if (isset($borrowersByDepartment[$department])) {
                    $borrowersByDepartment[$department]++;
                } else {
                    $borrowersByDepartment[$department] = 1;
                }
            }
    
            // Increment count by gender
            $gender = $user->gender == 1 ? 'Male' : 'Female'; // Assuming gender 1 represents Male and 2 represents Female
            $borrowersByGender[$gender]++;
        }
    
        // Return the response as JSON
        return response()->json([
            'borrowersByDepartment' => $borrowersByDepartment,
            'borrowersByGender' => $borrowersByGender
        ]);
    }
    

    public function mostBorrowed(Request $request){
        $mostBorrowedBooks = BorrowMaterial::select('book_id', DB::raw('COUNT(*) as borrow_count'))
            ->groupBy('book_id')
            ->orderByDesc('borrow_count')
            ->get();
    
        return response()->json($mostBorrowedBooks);
    }
        
    public function topborrowers(Request $request){
        $topborrowers = BorrowMaterial::select(
            'user_id',
            DB::raw('COUNT(*) as borrow_count'),
            'users.last_name',
            'users.first_name',
        )
        ->join('users', 'borrow_materials.user_id', '=', 'users.id')
        ->join('programs', 'borrow_materials.user_id', '=', 'users.id')
        ->groupBy('borrow_materials.user_id', 'users.last_name', 'users.first_name')
        ->orderByDesc('borrow_count')
        ->get();

        return response()->json($topborrowers,200);
    }

    public function getByUserId(Request $request, $userId)
    {
        $borrowMaterial = BorrowMaterial::with('book')->where('user_id', $userId)
                                        ->orderBy('borrow_expiration', 'asc')
                                        ->get();

        foreach($borrowMaterial as $book) {
            if($book->book->image_url != null)
                $book->book->image_url = self::URL . Storage::url($book->book->image_url);

            // $book->book->authors = json_decode($book->book->authors);
            // $book->book->authors = 'sup';
        }

        if ($borrowMaterial->isEmpty()) {
            return response()->json(['message' => 'No borrow records found for the user'], 404);
        }

        return response()->json($borrowMaterial, 200);
    }
}