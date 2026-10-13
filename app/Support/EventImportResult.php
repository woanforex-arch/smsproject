<?php

namespace App\Support;

class EventImportResult
{
    /**
     * @param  array<int, string>  $errors  Row number => reason.
     */
    public function __construct(
        public readonly int $created = 0,
        public readonly array $errors = [],
        public readonly int $skipped = 0,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** A short, human-readable summary of the first few failures. */
    public function errorSummary(int $limit = 5): string
    {
        return collect($this->errors)
            ->take($limit)
            ->map(fn (string $reason, int $line): string => "row {$line}: {$reason}")
            ->implode('; ');
    }
}
