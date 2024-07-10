<?php

namespace App\Http\Controllers\Cataloging;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Http\Controllers\ImageController;
use Exception;
use Illuminate\Http\Request;
use App\Models\Location;
use Storage, Str, DB;

class MaterialViewController extends Controller
{
    // const URL = 'http://26.68.32.39:8000';
    const URL = 'http://127.0.0.1:8000';

    public function getMaterials(String $type) {    
    
        switch($type) {
            case 'books':
                $materials = Material::where('material_type', $type)->orderByDesc('updated_at')
                ->get(['accession', 'title', 'authors', 'location', 'copyright']);
                break;
            
            case 'periodicals':
                $materials = Material::where([['material_type', 1], ['periodical_type', 0]])
                ->orderByDesc('updated_at')
                ->get(['accession', 'title', 'author', 'publisher', 'copyright']);
                break;
            
            case 'articles':
                $materials = Material::where([['material_type', 2]])
                ->orderByDesc('updated_at')
                ->get(['accession', 'title', 'author', 'publisher', 'date_published', 'copyright']);
                break;

            case 'audio-visuals':
                $materials = Material::where([['material_type', 3]])
                ->orderByDesc('updated_at')
                ->get(['accession', 'title', 'authors', 'copyright']);
                break;
            
            default:
                return response()->json(['response' => 'Invalid material type']);

            foreach($materials as $material) {
                if($material->image_url != null)
                    $material->image_url = self::URL .  Storage::url($material->image_url);

                if($material->authors)
                    $material->authors = json_decode($material->authors);
            }

            return $materials;
        }
        

        foreach($materials as $material) {
            $material->authors = json_decode($material->authors);
        }
    
        return $materials;
    }

    public function getMaterialsByType(String $type, String $periodical_type) {
        $columns = [];
        switch($type) {
            case 'periodicals':
                $material_type = 1;
                $columns = ['accession', 'title', 'authors', 'publisher', 'copyright'];
                break;

            case 'articles':
                $material_type = 2;
                $columns = ['accession', 'title', 'authors', 'publisher', 'date_published', 'copyright'];
                break;

            default:
                return response()->json(['response' => 'Invalid material type']);
        }
        
        $materials = Material::where([['material_type', $material_type], ['periodical_type', $periodical_type]])
        ->orderByDesc('updated_at')
        ->get($columns);        

        foreach($materials as $material) {
            if($material->image_url != null)
                $material->image_url = self::URL . Storage::url($material->image_url);

            $material->authors = json_decode($material->authors);
        }
        
        return $materials;
    }

    public function getMaterial($id) {
        $material = Material::where('accession', $id)->firstOrFail();
        $material->authors = json_decode($material->authors);
        if($material->image_url != null)
            $material->image_url = self::URL . Storage::url($material->image_url);

        return $material;
    }
}

