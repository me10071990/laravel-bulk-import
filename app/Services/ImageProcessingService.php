<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;

class ImageProcessingService
{
    private array $variants = [
        '256px' => 256,
        '512px' => 512,
        '1024px' => 1024
    ];

   
    public function processUpload(Upload $upload, $imageable): array
    {
        if (!$upload->isCompleted()) {
            throw new \Exception('Upload is not completed');
        }
        
        $images = [];
        
        // Store original
        $originalImage = $this->storeOriginal($upload, $imageable);
        $images[] = $originalImage;
        
        // Generate variants with aspect ratio preserved
        foreach ($this->variants as $variantName => $maxSize) {
            $variantImage = $this->generateVariant($upload, $imageable, $variantName, $maxSize);
            $images[] = $variantImage;
        }
        
        return $images;
    }

  
    private function storeOriginal(Upload $upload, $imageable): Image
    {
        $originalPath = $upload->storage_path;
        $fullPath = Storage::path($originalPath);
        
       
        $imageInfo = getimagesize($fullPath);
        $fileSize = Storage::size($originalPath);
        
        return Image::create([
            'upload_id' => $upload->id,
            'imageable_type' => get_class($imageable),
            'imageable_id' => $imageable->id,
            'variant' => 'original',
            'path' => $originalPath,
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'size' => $fileSize
        ]);
    }

  
    private function generateVariant(Upload $upload, $imageable, string $variantName, int $maxSize): Image
    {
        $originalPath = Storage::path($upload->storage_path);
        
    
        $image = InterventionImage::read($originalPath);
        
    
        $image->scale(width: $maxSize, height: $maxSize);
        

        $variantPath = "uploads/{$upload->upload_id}/variants/{$variantName}_{$upload->filename}";
        $variantFullPath = Storage::path($variantPath);
        
      
        $directory = dirname($variantFullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
     
        $image->save($variantFullPath);
        

        $variantSize = filesize($variantFullPath);
        $width = $image->width();
        $height = $image->height();
        
        return Image::create([
            'upload_id' => $upload->id,
            'imageable_type' => get_class($imageable),
            'imageable_id' => $imageable->id,
            'variant' => $variantName,
            'path' => $variantPath,
            'width' => $width,
            'height' => $height,
            'size' => $variantSize
        ]);
    }

  
    public function attachAsPrimary(Image $image, $entity): bool
    {
      
        $existingImage = Image::where('upload_id', $image->upload_id)
            ->where('imageable_type', get_class($entity))
            ->where('imageable_id', $entity->id)
            ->where('variant', 'original')
            ->first();
        
        if ($existingImage && $entity->primary_image_id === $existingImage->id) {
            return false; // Already attached, no-op
        }
        
    
        $entity->setPrimaryImage($image);
        
        return true;
    }
}
