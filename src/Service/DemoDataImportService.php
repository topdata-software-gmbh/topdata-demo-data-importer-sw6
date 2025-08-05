<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Topdata\TopdataDemoDataImporterSW6\Service\ProductService;

/**
 * Handles the import of demo data from a CSV file.
 * 07/2024 created (extracted from ProductService)
 */
class DemoDataImportService
{
    private ?int $columnNumber = null;
    private ?int $columnName = null;
    private ?int $columnEAN = null;
    private ?int $columnMPN = null;
    private string $divider;
    private string $trim;

    public function __construct(
        private readonly ProductService $productService
    )
    {
    }

    /**
     * Installs demo data from a CSV file.
     * 10/2024 extracted from ProductService
     *
     * @param string $filename The name of the CSV file to import. Defaults to 'demo-products.csv'.
     * @param string|null $categoryId Optional category ID to assign products to.
     * @return array An array containing the import status and additional information.
     */
    public function installDemoData(string $filename = 'demo-products.csv', ?string $categoryId = null): array
    {
        $this->divider = ';';
        $this->trim = '"';
        if (!$filename) {
            return [
                'success'        => false,
                'additionalInfo' => 'file with demo data not found!',
            ];
        }

        $file = __DIR__ . '/../Resources/demo-data/' . $filename;
        $handle = fopen($file, 'r');
        if (!$handle) {
            return [
                'success'        => false,
                'additionalInfo' => 'file with demo not accessible!',
            ];
        }

        $line = fgets($handle);
        if ($line === false) {
            return [
                'success'        => false,
                'additionalInfo' => 'file is empty!',
            ];
        }

        $values = explode($this->divider, $line);

        // ---- Determine column indices based on header row
        foreach ($values as $key => $val) {
            $val = trim($val);
            if ($val === 'article_no') {
                $this->columnNumber = $key;
            }
            if ($val === 'short_desc') {
                $this->columnName = $key;
            }
            if ($val === 'ean') {
                $this->columnEAN = $key;
            }
            if ($val === 'oem') {
                $this->columnMPN = $key;
            }
        }

        if (is_null($this->columnNumber)) {
            return [
                'success'        => false,
                'additionalInfo' => 'article_no column not exists!',
            ];
        }

        if (is_null($this->columnName)) {
            return [
                'success'        => false,
                'additionalInfo' => 'short_desc column not exists!',
            ];
        }

        if (is_null($this->columnEAN)) {
            return [
                'success'        => false,
                'additionalInfo' => 'ean column not exists!',
            ];
        }

        if (is_null($this->columnMPN)) {
            return [
                'success'        => false,
                'additionalInfo' => 'oem column not exists!',
            ];
        }

        $products = [];

        // ---- Read and process each line of the CSV file
        while (($line = fgets($handle)) !== false) {
            $values = explode($this->divider, $line);
            foreach ($values as $key => $val) {
                $values[$key] = trim($val, $this->trim);
            }
            $products[$values[$this->columnNumber]] = [
                'productNumber' => trim($values[$this->columnNumber]),
                'name'          => trim($values[$this->columnName]),
                'ean'           => trim($values[$this->columnEAN]),
                'mpn'           => trim($values[$this->columnMPN]),
            ];
        }

        fclose($handle);

        // ---- Clear existing products and format the product array
        $products = $this->productService->clearExistingProductsByProductNumber($products);
        if (count($products)) {
            $products = $this->productService->formProductsArray($products, 100000.0, $categoryId);
        } else {
            return [
                'success'        => true,
                'additionalInfo' => 'Nothing to add',
            ];
        }

        // ---- Create the products
        $this->productService->createProducts($products);

        return [
            'success'          => true,
            'additionalInfo'   => count($products) . ' products has been added',
            'importedProducts' => array_map(function ($product) {
                return [
                    'productNumber' => $product['productNumber'],
                    'name'          => $product['name'][array_key_first($product['name'])],
                    'ean'           => $product['ean'] ?? null,
                    'mpn'           => $product['manufacturerNumber'] ?? null,
                ];
            }, $products)
        ];
    }
}