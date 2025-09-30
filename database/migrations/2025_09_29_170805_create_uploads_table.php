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
        Schema::create('uploads', function (Blueprint $table) {
           $table->id();
            $table->string('upload_id')->unique()->comment('UUID for tracking');
            $table->string('filename');
            $table->string('mime_type');
            $table->bigInteger('total_size')->comment('Total file size in bytes');
            $table->bigInteger('uploaded_size')->default(0);
            $table->integer('total_chunks');
            $table->integer('uploaded_chunks')->default(0);
            $table->string('checksum')->nullable()->comment('MD5 checksum');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('storage_path')->nullable();
            $table->timestamps();
            
            $table->index('upload_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
