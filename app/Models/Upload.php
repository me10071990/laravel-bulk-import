<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Upload extends Model
{
   use HasFactory;

    protected $fillable = [
        'upload_id',
        'filename',
        'mime_type',
        'total_size',
        'uploaded_size',
        'total_chunks',
        'uploaded_chunks',
        'checksum',
        'status',
        'storage_path'
    ];

    protected $casts = [
        'total_size' => 'integer',
        'uploaded_size' => 'integer',
        'total_chunks' => 'integer',
        'uploaded_chunks' => 'integer'
    ];
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
    public function allChunksUploaded(): bool
    {
        return $this->uploaded_chunks >= $this->total_chunks;
    }
    public function markAsCompleted(string $storagePath): void
    {
        $this->update([
            'status' => 'completed',
            'storage_path' => $storagePath
        ]);
    }
    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

     public function recordChunkUpload(int $chunkSize): void
    {
        $this->increment('uploaded_chunks');
        $this->increment('uploaded_size', $chunkSize);
    }

     public function getProgressPercentage(): float
    {
        if ($this->total_size === 0) {
            return 0;
        }
        
        return round(($this->uploaded_size / $this->total_size) * 100, 2);
    }

    
}
