<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadService
{
    private string $tempDirectory = 'temp/uploads';

   
    public function initializeUpload(
        string $filename, 
        string $mimeType, 
        int $totalSize, 
        int $totalChunks, 
        string $checksum
    ): Upload {
        $uploadId = Str::uuid()->toString();
        
        return Upload::create([
            'upload_id' => $uploadId,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'total_size' => $totalSize,
            'total_chunks' => $totalChunks,
            'checksum' => $checksum,
            'status' => 'pending'
        ]);
    }

  
    public function uploadChunk(Upload $upload, UploadedFile $chunk, int $chunkIndex): bool
    {
        return DB::transaction(function () use ($upload, $chunk, $chunkIndex) {
          
            $upload->refresh();
            
            $chunkPath = $this->getChunkPath($upload->upload_id, $chunkIndex);
            
          
            if (Storage::exists($chunkPath)) {
                return true; // Already uploaded, skip
            }
            
         
            Storage::put($chunkPath, $chunk->get());
            
          
            $upload->recordChunkUpload($chunk->getSize());
            
            return true;
        });
    }

   
  public function completeUpload(Upload $upload): bool
{
    \Log::info('Complete upload called', [
        'upload_id' => $upload->upload_id,
        'uploaded_chunks' => $upload->uploaded_chunks,
        'total_chunks' => $upload->total_chunks
    ]);
    
  
    if (!$upload->allChunksUploaded()) {
        \Log::error('Not all chunks uploaded', [
            'uploaded' => $upload->uploaded_chunks,
            'total' => $upload->total_chunks
        ]);
        return false;
    }
    
  
    $upload->update(['status' => 'processing']);
    
   
    $assembledPath = $this->assembleChunks($upload);
    
    if (!$assembledPath) {
        \Log::error('Failed to assemble chunks');
        $upload->markAsFailed();
        return false;
    }
    
    \Log::info('Chunks assembled', ['path' => $assembledPath]);
    
    
    if (!$this->verifyChecksum($assembledPath, $upload->checksum)) {
        \Log::error('Checksum mismatch', [
            'expected' => $upload->checksum,
            'file_path' => $assembledPath
        ]);
        Storage::delete($assembledPath);
        $this->cleanupChunks($upload->upload_id);
        $upload->markAsFailed();
        return false;
    }
    
    \Log::info('Checksum verified');
    
   
    $finalPath = "uploads/{$upload->upload_id}/{$upload->filename}";
    Storage::move($assembledPath, $finalPath);
    
   
    $upload->markAsCompleted($finalPath);
    

    $this->cleanupChunks($upload->upload_id);
    
    \Log::info('Upload completed successfully');
    
    return true;
}

   
    private function assembleChunks(Upload $upload): ?string
{
    $assembledPath = "{$this->tempDirectory}/{$upload->upload_id}/assembled";
    
 
    $directory = dirname(Storage::path($assembledPath));
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    $fullPath = Storage::path($assembledPath);
    $handle = fopen($fullPath, 'wb');
    
    if (!$handle) {
        \Log::error('Could not open file for writing', ['path' => $fullPath]);
        return null;
    }
    
    for ($i = 0; $i < $upload->total_chunks; $i++) {
        $chunkPath = $this->getChunkPath($upload->upload_id, $i);
        
        if (!Storage::exists($chunkPath)) {
            \Log::error('Missing chunk', ['chunk_index' => $i, 'path' => $chunkPath]);
            fclose($handle);
            return null;
        }
        
        $chunkContent = Storage::get($chunkPath);
        fwrite($handle, $chunkContent);
    }
    
    fclose($handle);
    
    \Log::info('File assembled', [
        'path' => $assembledPath,
        'size' => filesize($fullPath)
    ]);
    
    return $assembledPath;
}

   
    private function verifyChecksum(string $path, string $expectedChecksum): bool
{
    $fileContent = Storage::get($path);
    $actualChecksum = hash('sha256', $fileContent);
    
    \Log::info('Checksum verification', [
        'expected' => $expectedChecksum,
        'actual' => $actualChecksum,
        'match' => hash_equals($expectedChecksum, $actualChecksum)
    ]);
    
    return hash_equals($expectedChecksum, $actualChecksum);
}
   
    private function getChunkPath(string $uploadId, int $chunkIndex): string
    {
        return "{$this->tempDirectory}/{$uploadId}/chunk_{$chunkIndex}";
    }

  
    private function cleanupChunks(string $uploadId): void
    {
        Storage::deleteDirectory("{$this->tempDirectory}/{$uploadId}");
    }


    public function getUploadStatus(string $uploadId): ?Upload
    {
        return Upload::where('upload_id', $uploadId)->first();
    }
}
