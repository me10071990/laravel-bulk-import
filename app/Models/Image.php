<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
     use HasFactory;

    protected $fillable = [
        'upload_id',
        'imageable_type',
        'imageable_id',
        'variant',
        'path',
        'width',
        'height',
        'size'
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer'
    ];


    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

     public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

     public function getUrl(): string
    {
        return Storage::url($this->path);
    }

    public function isOriginal(): bool
    {
        return $this->variant === 'original';
    }

    public function getHumanFileSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
