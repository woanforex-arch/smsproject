<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Support\EventImportResult;
use App\Support\Spreadsheet;
use Carbon\Carbon;
use RuntimeException;
use Throwable;

/**
 * Turns an uploaded spreadsheet into Event records.
 *
 * The first row must be a header naming the columns. Header names are matched
 * loosely (case, spaces and underscores are ignored) and dates may be supplied
 * either as text or as native Excel date cells.
 */
class EventImportService
{
    /** Normalised header name => Event attribute. */
    protected const ALIASES = [
        'title' => 'title',
        'name' => 'title',
        'event' => 'title',
        'event title' => 'title',
        'slug' => 'slug',
        'url' => 'slug',
        'description' => 'description',
        'details' => 'description',
        'venue' => 'venue',
        'location' => 'venue',
        'address' => 'address',
        'city' => 'city',
        'town' => 'city',
        'starts at' => 'starts_at',
        'starts' => 'starts_at',
        'start' => 'starts_at',
        'start date' => 'starts_at',
        'start datetime' => 'starts_at',
        'start time' => 'starts_at',
        'ends at' => 'ends_at',
        'ends' => 'ends_at',
        'end' => 'ends_at',
        'end date' => 'ends_at',
        'end datetime' => 'ends_at',
        'end time' => 'ends_at',
        'registration deadline' => 'registration_deadline',
        'registration closes' => 'registration_deadline',
        'deadline' => 'registration_deadline',
        'status' => 'status',
        'capacity' => 'capacity',
    ];

    public function import(string $path): EventImportResult
    {
        $rows = Spreadsheet::rows($path);

        if ($rows === []) {
            throw new RuntimeException('The spreadsheet is empty.');
        }

        $map = $this->mapHeader(array_shift($rows));

        if ($map === []) {
            throw new RuntimeException(
                'No header row was recognised. The first row should name the columns '
                .'(for example: title, starts_at, ends_at, venue, city, capacity).'
            );
        }

        $created = 0;
        $errors = [];

        foreach ($rows as $offset => $row) {
            $line = $offset + 2; // One-based row number, plus the header row.

            if ($this->isBlank($row)) {
                continue;
            }

            try {
                Event::create($this->attributes($row, $map));
                $created++;
            } catch (Throwable $e) {
                $errors[$line] = $e->getMessage();
            }
        }

        return new EventImportResult($created, $errors, count($errors));
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int>  Event attribute => column index.
     */
    protected function mapHeader(array $header): array
    {
        $map = [];

        foreach ($header as $index => $cell) {
            $attribute = static::ALIASES[$this->normalise((string) $cell)] ?? null;

            if ($attribute !== null && ! isset($map[$attribute])) {
                $map[$attribute] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $map
     * @return array<string, mixed>
     */
    protected function attributes(array $row, array $map): array
    {
        $title = $this->cell($row, $map, 'title');

        if ($title === null) {
            throw new RuntimeException('Missing the event title.');
        }

        $startsAt = $this->date($this->cell($row, $map, 'starts_at'));
        $endsAt = $this->date($this->cell($row, $map, 'ends_at'));

        if ($startsAt === null) {
            throw new RuntimeException('Missing or invalid start date (starts_at).');
        }

        if ($endsAt === null) {
            throw new RuntimeException('Missing or invalid end date (ends_at).');
        }

        return [
            'title' => $title,
            'slug' => Event::uniqueSlug($this->cell($row, $map, 'slug') ?: $title),
            'description' => $this->cell($row, $map, 'description'),
            'venue' => $this->cell($row, $map, 'venue'),
            'address' => $this->cell($row, $map, 'address'),
            'city' => $this->cell($row, $map, 'city'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'registration_deadline' => $this->date($this->cell($row, $map, 'registration_deadline')),
            'status' => $this->status($this->cell($row, $map, 'status')),
            'capacity' => $this->integer($this->cell($row, $map, 'capacity')),
            'created_by' => auth()->id(),
        ];
    }

    /** @param array<int, string|null> $row @param array<string, int> $map */
    protected function cell(array $row, array $map, string $attribute): ?string
    {
        if (! isset($map[$attribute])) {
            return null;
        }

        return $this->string($row[$map[$attribute]] ?? null);
    }

    protected function date(?string $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        // Native Excel date cells arrive as a serial number (days since 1900).
        if (is_numeric($raw)) {
            $serial = (float) $raw;

            if ($serial > 20000 && $serial < 80000) {
                return Carbon::create(1899, 12, 30, 0, 0, 0, config('app.timezone'))
                    ->addSeconds((int) round($serial * 86400));
            }

            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    protected function status(?string $value): string
    {
        if ($value === null) {
            return EventStatus::Draft->value;
        }

        foreach (EventStatus::cases() as $case) {
            if (in_array(strtolower($value), [$case->value, strtolower($case->label())], true)) {
                return $case->value;
            }
        }

        throw new RuntimeException("Unknown status \"{$value}\".");
    }

    protected function integer(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new RuntimeException("The capacity \"{$value}\" is not a number.");
        }

        return max(1, (int) $value);
    }

    protected function string(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalise(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace(['_', '-'], ' ', $header);

        return trim((string) preg_replace('/\s+/', ' ', $header));
    }

    /** @param array<int, string|null> $row */
    protected function isBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
