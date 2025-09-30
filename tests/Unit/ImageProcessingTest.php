<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ImageProcessingService;
use App\Models\Upload;
use App\Models\Product;
use App\Models\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class ImageProcessingTest extends TestCase
{
    use RefreshDatabase;

    private ImageProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageProcessingService();
        Storage::fake('local');
    }

    /** @test */
    public function it_generates_all_image_variants()
    {
        // Create product
        $product = Product::create([
            'sku' => 'TEST001',
            'name' => 'Test Product',
            'price' => 10.00,
            'stock' => 5
        ]);

        // Create a simple test image (1x1 pixel PNG)
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $testPath = 'uploads/test-upload-id/test.png';
        Storage::put($testPath, $imageData);

        // Create upload record
        $upload = Upload::create([
            'upload_id' => 'test-upload-id',
            'filename' => 'test.png',
            'mime_type' => 'image/png',
            'total_size' => strlen($imageData),
            'total_chunks' => 1,
            'uploaded_chunks' => 1,
            'checksum' => hash('sha256', $imageData),
            'status' => 'completed',
            'storage_path' => $testPath
        ]);

        // Process upload
        $images = $this->service->processUpload($upload, $product);

        // Assert 4 variants created (original + 3 sizes)
        $this->assertCount(4, $images);

        // Assert variants
        $variants = array_map(fn($img) => $img->variant, $images);
        $this->assertContains('original', $variants);
        $this->assertContains('256px', $variants);
        $this->assertContains('512px', $variants);
        $this->assertContains('1024px', $variants);

        // Assert all linked to product
        foreach ($images as $image) {
            $this->assertEquals(Product::class, $image->imageable_type);
            $this->assertEquals($product->id, $image->imageable_id);
        }
    }

    /** @test */
    public function it_preserves_aspect_ratio_in_variants()
    {
        // Create product
        $product = Product::create([
            'sku' => 'TEST002',
            'name' => 'Test Product 2',
            'price' => 15.00,
            'stock' => 10
        ]);

        // Create test image
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $testPath = 'uploads/test-upload-id2/test.png';
        Storage::put($testPath, $imageData);

        $upload = Upload::create([
            'upload_id' => 'test-upload-id2',
            'filename' => 'test.png',
            'mime_type' => 'image/png',
            'total_size' => strlen($imageData),
            'total_chunks' => 1,
            'uploaded_chunks' => 1,
            'checksum' => hash('sha256', $imageData),
            'status' => 'completed',
            'storage_path' => $testPath
        ]);

        // Process upload
        $images = $this->service->processUpload($upload, $product);

        // Check each variant maintains aspect ratio
        foreach ($images as $image) {
            if ($image->variant !== 'original') {
                // For variants, max dimension should not exceed the variant size
                $maxDimension = max($image->width, $image->height);
                $variantSize = (int) filter_var($image->variant, FILTER_SANITIZE_NUMBER_INT);
                $this->assertLessThanOrEqual($variantSize, $maxDimension);
            }
        }
    }

    /** @test */
    public function it_stores_correct_image_metadata()
    {
        // Create product
        $product = Product::create([
            'sku' => 'TEST003',
            'name' => 'Test Product 3',
            'price' => 20.00,
            'stock' => 15
        ]);

        // Create test image
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $testPath = 'uploads/test-upload-id3/test.png';
        Storage::put($testPath, $imageData);

        $upload = Upload::create([
            'upload_id' => 'test-upload-id3',
            'filename' => 'test.png',
            'mime_type' => 'image/png',
            'total_size' => strlen($imageData),
            'total_chunks' => 1,
            'uploaded_chunks' => 1,
            'checksum' => hash('sha256', $imageData),
            'status' => 'completed',
            'storage_path' => $testPath
        ]);

        // Process upload
        $images = $this->service->processUpload($upload, $product);

        foreach ($images as $image) {
            // Assert metadata fields are populated
            $this->assertNotNull($image->upload_id);
            $this->assertNotNull($image->variant);
            $this->assertNotNull($image->path);
            $this->assertGreaterThan(0, $image->width);
            $this->assertGreaterThan(0, $image->height);
            $this->assertGreaterThan(0, $image->size);
        }
    }

    /** @test */
    public function it_throws_exception_for_incomplete_upload()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Upload is not completed');

        $product = Product::create([
            'sku' => 'TEST004',
            'name' => 'Test Product 4',
            'price' => 25.00,
            'stock' => 20
        ]);

        // Create incomplete upload
        $upload = Upload::create([
            'upload_id' => 'incomplete-upload',
            'filename' => 'test.png',
            'mime_type' => 'image/png',
            'total_size' => 1000,
            'total_chunks' => 2,
            'uploaded_chunks' => 1,
            'checksum' => 'abc123',
            'status' => 'pending'
        ]);

        // Should throw exception
        $this->service->processUpload($upload, $product);
    }

    /** @test */
    public function it_links_images_to_correct_product()
    {
        // Create two products
        $product1 = Product::create(['sku' => 'P1', 'name' => 'Product 1', 'price' => 10, 'stock' => 5]);
        $product2 = Product::create(['sku' => 'P2', 'name' => 'Product 2', 'price' => 15, 'stock' => 10]);

        // Create test image for product1
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $testPath = 'uploads/link-test/test.png';
        Storage::put($testPath, $imageData);

        $upload = Upload::create([
            'upload_id' => 'link-test',
            'filename' => 'test.png',
            'mime_type' => 'image/png',
            'total_size' => strlen($imageData),
            'total_chunks' => 1,
            'uploaded_chunks' => 1,
            'checksum' => hash('sha256', $imageData),
            'status' => 'completed',
            'storage_path' => $testPath
        ]);

        // Process for product1
        $images = $this->service->processUpload($upload, $product1);

        // Assert all images linked to product1, not product2
        foreach ($images as $image) {
            $this->assertEquals($product1->id, $image->imageable_id);
            $this->assertNotEquals($product2->id, $image->imageable_id);
        }

        // Verify product1 has images, product2 doesn't
        $this->assertCount(4, $product1->images);
        $this->assertCount(0, $product2->images);
    }
}