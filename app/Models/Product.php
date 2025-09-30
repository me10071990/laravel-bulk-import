<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'stock',
        'primary_image_id'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer'
    ];

     public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function primaryImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'primary_image_id');
    }

    public function setPrimaryImage(Image $image): bool
    {
        if ($this->primary_image_id === $image->id) {
            return false; 
        }
        
        $this->primary_image_id = $image->id;
        $this->save();
        
        return true;
    }

    public function getOriginalImage(): ?Image
    {
        return $this->images()->where('variant', 'original')->first();
    }
}
