<?php

namespace Topdata\TopdataDemoDataImporterSW6\DTO;

/**
 * Represents the configuration for importing data from a CSV file.
 * Defines the delimiter, enclosure, start line, end line, and column mapping.
 */
class CsvConfiguration
{

    /**
     * @param array<string, int|null> $columnMapping An array mapping column names to their respective index in the CSV file. Null indicates the column should be skipped.
     */
    public function __construct(
        private readonly string $delimiter,
        private readonly string $enclosure,
        private readonly int    $startLine,
        private readonly ?int   $endLine,
        private readonly array  $columnMapping
    )
    {
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
     * Returns the column mapping configuration.
     *
     * @return array<string, int|null>
     */
    public function getColumnMapping(): array
    {
        return $this->columnMapping;
    }
}