<?php

namespace App\Http\Controllers;

use App\Services\CsvImportService;
use App\Services\ImageProcessingService;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImportController extends Controller
{
    private CsvImportService $csvImportService;
    private ImageProcessingService $imageProcessingService;

    public function __construct(
        CsvImportService $csvImportService,
        ImageProcessingService $imageProcessingService
    ) {
        $this->csvImportService = $csvImportService;
        $this->imageProcessingService = $imageProcessingService;
    }

   
    public function index()
    {
        return view('import.index');
    }

   
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:20480'
        ]);

        $file = $request->file('csv_file');
        
        // Store temporarily
        $path = $file->storeAs('temp', 'import_' . time() . '.csv');
        $fullPath = Storage::path($path);
        
        // Import products
        $summary = $this->csvImportService->importProducts($fullPath);
        
        // Cleanup temp file
        Storage::delete($path);
        
        return response()->json([
            'success' => true,
            'message' => 'Import completed successfully',
            'summary' => $summary
        ]);
    }

   
    public function linkImage(Request $request)
    {
        $request->validate([
            'product_sku' => 'required|exists:products,sku',
            'upload_id' => 'required|exists:uploads,upload_id'
        ]);

        $product = Product::where('sku', $request->product_sku)->first();
        $upload = Upload::where('upload_id', $request->upload_id)->first();

        if (!$upload->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Upload is not completed yet'
            ], 400);
        }

     
        $images = $this->imageProcessingService->processUpload($upload, $product);
        
   
        $originalImage = collect($images)->firstWhere('variant', 'original');
        $this->imageProcessingService->attachAsPrimary($originalImage, $product);

        return response()->json([
            'success' => true,
            'message' => 'Image linked successfully',
            'images_count' => count($images)
        ]);
    }

   
    public function products()
    {
        $products = Product::with('primaryImage')->paginate(20);
        
        return response()->json($products);
    }
}
