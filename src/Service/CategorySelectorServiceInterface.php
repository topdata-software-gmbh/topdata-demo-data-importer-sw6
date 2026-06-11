<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

interface CategorySelectorServiceInterface
{
    /**
     * @return array<string, string> Key: category ID, Value: formatted breadcrumb name.
     */
    public function getCategoryChoices(): array;

    public function getCategoryName(string $categoryId): ?string;
}
