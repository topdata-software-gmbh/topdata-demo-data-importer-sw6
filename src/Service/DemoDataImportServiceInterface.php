<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

interface DemoDataImportServiceInterface
{
    public function installDemoData(string $filename = 'demo-products.csv', ?string $categoryId = null): array;
}
