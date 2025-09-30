<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CsvImportService;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private CsvImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CsvImportService();
    }

    /** @test */
    public function it_imports_new_products_from_csv()
    {
        // Create test CSV
        $csvPath = storage_path('app/test_import_unit.csv');
        $csvContent = "sku,name,description,price,stock\n";
        $csvContent .= "TEST001,Test Product,Test Description,25.99,100\n";
        file_put_contents($csvPath, $csvContent);

        // Import
        $summary = $this->service->importProducts($csvPath);

        // Assert
        $this->assertEquals(1, $summary['total']);
        $this->assertEquals(1, $summary['imported']);
        $this->assertEquals(0, $summary['updated']);
        $this->assertEquals(0, $summary['invalid']);

        // Verify in database
        $product = Product::where('sku', 'TEST001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals(25.99, $product->price);
        $this->assertEquals(100, $product->stock);

        // Cleanup
        unlink($csvPath);
    }

    /** @test */
    public function it_updates_existing_products_via_upsert()
    {
        // Create initial product
        $product = Product::create([
            'sku' => 'TEST002',
            'name' => 'Original Name',
            'description' => 'Original Description',
            'price' => 10.00,
            'stock' => 50
        ]);

        // Create CSV with updated data
        $csvPath = storage_path('app/test_update_unit.csv');
        $csvContent = "sku,name,description,price,stock\n";
        $csvContent .= "TEST002,Updated Name,Updated Description,20.00,75\n";
        file_put_contents($csvPath, $csvContent);

        // Import (should update)
        $summary = $this->service->importProducts($csvPath);

        // Assert counts
        $this->assertEquals(1, $summary['total']);
        $this->assertEquals(0, $summary['imported']);
        $this->assertEquals(1, $summary['updated']);

        // Verify update in database
        $product->refresh();
        $this->assertEquals('Updated Name', $product->name);
        $this->assertEquals(20.00, $product->price);
        $this->assertEquals(75, $product->stock);

        // Cleanup
        unlink($csvPath);
    }

    /** @test */
    public function it_marks_rows_with_missing_required_columns_as_invalid()
    {
        // Create CSV with missing required column (price)
        $csvPath = storage_path('app/test_invalid_unit.csv');
        $csvContent = "sku,name,description,stock\n";
        $csvContent .= "TEST003,Test Product,Test Description,100\n";
        file_put_contents($csvPath, $csvContent);

        // Import
        $summary = $this->service->importProducts($csvPath);

        // Assert
        $this->assertEquals(1, $summary['total']);
        $this->assertEquals(0, $summary['imported']);
        $this->assertEquals(1, $summary['invalid']);

        // Verify NOT in database
        $product = Product::where('sku', 'TEST003')->first();
        $this->assertNull($product);

        // Cleanup
        unlink($csvPath);
    }

    /** @test */
    public function it_detects_duplicate_skus_within_csv()
    {
        // Create CSV with duplicate SKU
        $csvPath = storage_path('app/test_duplicate_unit.csv');
        $csvContent = "sku,name,description,price,stock\n";
        $csvContent .= "TEST004,First Entry,Description,10.00,50\n";
        $csvContent .= "TEST004,Duplicate Entry,Description,15.00,75\n";
        file_put_contents($csvPath, $csvContent);

        // Import
        $summary = $this->service->importProducts($csvPath);

        // Assert - first should import, second marked as duplicate
        $this->assertEquals(2, $summary['total']);
        $this->assertEquals(1, $summary['imported']);
        $this->assertEquals(1, $summary['duplicates']);

        // Verify only first entry in database
        $product = Product::where('sku', 'TEST004')->first();
        $this->assertNotNull($product);
        $this->assertEquals('First Entry', $product->name);

        // Cleanup
        unlink($csvPath);
    }

    /** @test */
    public function it_handles_mixed_operations_correctly()
    {
        // Create existing product
        Product::create([
            'sku' => 'EXIST001',
            'name' => 'Existing',
            'price' => 5.00,
            'stock' => 10
        ]);

        // Create CSV with mixed operations
        $csvPath = storage_path('app/test_mixed_unit.csv');
        $csvContent = "sku,name,description,price,stock\n";
        $csvContent .= "EXIST001,Updated Existing,Desc,7.50,20\n"; // Update
        $csvContent .= "NEW001,New Product,Desc,12.00,30\n"; // Insert
        $csvContent .= "INVALID,Missing Price,Desc,,40\n"; // Invalid
        $csvContent .= "NEW001,Duplicate,Desc,15.00,50\n"; // Duplicate
        file_put_contents($csvPath, $csvContent);

        // Import
        $summary = $this->service->importProducts($csvPath);

        // Assert summary
        $this->assertEquals(4, $summary['total']);
        $this->assertEquals(1, $summary['imported']);
        $this->assertEquals(1, $summary['updated']);
        $this->assertEquals(1, $summary['invalid']);
        $this->assertEquals(1, $summary['duplicates']);

        // Cleanup
        unlink($csvPath);
    }
}