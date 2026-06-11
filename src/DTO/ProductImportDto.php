<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\DTO;

class ProductImportDto
{
    public function __construct(
        private readonly string $productNumber,
        private readonly string $name,
        private readonly ?string $ean = null,
        private readonly ?string $mpn = null,
        private readonly ?string $description = null,
        private readonly ?string $topDataId = null,
        private readonly ?string $brand = null
    ) {
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function getMpn(): ?string
    {
        return $this->mpn;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTopDataId(): ?string
    {
        return $this->topDataId;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }
}
