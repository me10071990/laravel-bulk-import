<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {

            $table->id();
           $table->foreignId('upload_id')->constrained('uploads')->cascadeOnDelete();
            $table->morphs('imageable');
            $table->string('variant')->comment('original, 256px, 512px, 1024px');
            $table->string('path');
            $table->integer('width');
            $table->integer('height');
            $table->bigInteger('size')->comment('File size in bytes');
            $table->timestamps();
            
          
            $table->index('variant');

        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('primary_image_id')->references('id')->on('images')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['primary_image_id']);
        });
        
        Schema::dropIfExists('images');
    }
};
