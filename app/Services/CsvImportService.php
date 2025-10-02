<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;

class CsvImportService
{
    private array $summary = [
        'total' => 0,
        'imported' => 0,
        'updated' => 0,
        'invalid' => 0,
        'duplicates' => 0
    ];

    private array $processedSkus = [];

   
    public function importProducts(string $filePath): array
    {
     
        $this->summary = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'invalid' => 0,
            'duplicates' => 0
        ];
        $this->processedSkus = [];

        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        
        $records = $csv->getRecords();
        
        foreach ($records as $offset => $record) {
            $this->summary['total']++;
            
            
            $validation = $this->validateRow($record);
            
           if (!$validation['valid']) {
                $this->summary['invalid']++;
                \Log::info('Invalid row: ' . json_encode(['row' => $record, 'errors' => $validation['errors']]));
                continue;
            }
            
      
            $sku = trim($record['sku']);
            if (isset($this->processedSkus[$sku])) {
                $this->summary['duplicates']++;
                continue;
            }
            
            $this->processedSkus[$sku] = true;
            
         
            $this->upsertProduct($record);
        }
        
        return $this->summary;
    }

    private function validateRow(array $row): array
    {
        $requiredColumns = ['sku', 'name', 'price'];
        
     
        foreach ($requiredColumns as $column) {
            if (!isset($row[$column]) || trim($row[$column]) === '') {
                return [
                    'valid' => false, 
                    'errors' => ["Missing or empty required column: {$column}"]
                ];
            }
        }
        

        $validator = Validator::make($row, [
            'sku' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return [
                'valid' => false, 
                'errors' => $validator->errors()->all()
            ];
        }
        
        return ['valid' => true];
    }


    private function upsertProduct(array $data): void
    {
        $sku = trim($data['sku']);
        $product = Product::where('sku', $sku)->first();
        
        $productData = [
            'sku' => $sku,
            'name' => trim($data['name']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'price' => $data['price'],
            'stock' => isset($data['stock']) ? (int)$data['stock'] : 0
        ];
        
        if ($product) {
         
            $product->update($productData);
            $this->summary['updated']++;
        } else {
          
            Product::create($productData);
            $this->summary['imported']++;
        }
    }

   
    public function getSummary(): array
    {
        return $this->summary;
    }
}
