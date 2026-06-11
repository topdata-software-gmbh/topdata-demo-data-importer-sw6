<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\DTO;

/**
 * Data Transfer Object storing the custom parsing parameters for CSV product imports.
 */
class CsvConfiguration
{
    /**
     * @param array<string, int|null> $columnMapping Maps property names to CSV column indexes.
     */
    public function __construct(
        private readonly string $delimiter,
        private readonly string $enclosure,
        private readonly int    $startLine,
        private readonly ?int   $endLine,
        private readonly array  $columnMapping
    ) {
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getEnclosure(): string
    {
        return $this->enclosure;
    }

    public function getStartLine(): int
    {
        return $this->startLine;
    }

    public function getEndLine(): ?int
    {
        return $this->endLine;
    }

    /**
     * @return array<string, int|null>
     */
    public function getColumnMapping(): array
    {
        return $this->columnMapping;
    }
}
