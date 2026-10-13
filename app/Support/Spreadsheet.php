<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Minimal, dependency-free spreadsheet reader.
 *
 * Handles modern Excel workbooks (.xlsx, which are zip archives of XML) and
 * delimited text (.csv/.txt). It deliberately reads only the values — no
 * styling, formulas or number formats — because the importer deals with plain
 * data and converts dates itself.
 */
class Spreadsheet
{
    /**
     * Read a spreadsheet into a matrix of rows.
     *
     * @return array<int, array<int, string|null>>
     */
    public static function rows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('The uploaded file could not be found.');
        }

        return static::isZip($path)
            ? static::xlsx($path)
            : static::delimited($path);
    }

    protected static function isZip(string $path): bool
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return false;
        }

        $isXlsx = $zip->locateName('xl/workbook.xml') !== false
            || $zip->locateName('xl/worksheets/sheet1.xml') !== false;

        $zip->close();

        return $isXlsx;
    }

    /** @return array<int, array<int, string|null>> */
    protected static function xlsx(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The spreadsheet could not be opened.');
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheet === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheet = $zip->getFromName($name);
                    break;
                }
            }
        }

        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($sheet === false || $sheet === null) {
            throw new RuntimeException('No worksheet was found in the file.');
        }

        $shared = static::sharedStrings($sharedXml === false ? null : $sharedXml);

        return static::parseSheet($sheet, $shared);
    }

    /**
     * @return array<int, string>
     */
    protected static function sharedStrings(?string $xml): array
    {
        if ($xml === null || trim($xml) === '') {
            return [];
        }

        $dom = new DOMDocument();

        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $strings = [];

        foreach ($xpath->query('//*[local-name()="si"]') as $si) {
            $text = '';

            foreach ($xpath->query('.//*[local-name()="t"]', $si) as $t) {
                $text .= $t->nodeValue;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $shared
     * @return array<int, array<int, string|null>>
     */
    protected static function parseSheet(string $xml, array $shared): array
    {
        $dom = new DOMDocument();

        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('The worksheet could not be read.');
        }

        $xpath = new DOMXPath($dom);
        $rows = [];

        foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
            $cells = [];
            $fallback = 0;

            foreach ($xpath->query('./*[local-name()="c"]', $row) as $cell) {
                $reference = $cell->getAttribute('r');
                $index = $reference !== '' ? static::columnIndex($reference) : $fallback;

                $cells[$index] = static::cellValue($xpath, $cell, $shared);
                $fallback = $index + 1;
            }

            if ($cells === []) {
                $rows[] = [];

                continue;
            }

            $max = max(array_keys($cells));
            $line = [];

            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? null;
            }

            $rows[] = $line;
        }

        return $rows;
    }

    protected static function cellValue(DOMXPath $xpath, \DOMNode $cell, array $shared): ?string
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            $text = '';
            $is = $xpath->query('./*[local-name()="is"]', $cell)->item(0);

            if ($is !== null) {
                foreach ($xpath->query('.//*[local-name()="t"]', $is) as $t) {
                    $text .= $t->nodeValue;
                }
            }

            return $text;
        }

        $value = $xpath->query('./*[local-name()="v"]', $cell)->item(0);

        if ($value === null) {
            return null;
        }

        $raw = $value->nodeValue;

        return match ($type) {
            's' => $shared[(int) $raw] ?? null,
            'b' => $raw === '1' ? '1' : '0',
            default => $raw,
        };
    }

    /** Turn a cell reference like "AB12" into a zero-based column index. */
    protected static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';

        if ($letters === '') {
            return 0;
        }

        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /** @return array<int, array<int, string|null>> */
    protected static function delimited(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The file could not be read.');
        }

        // Drop a UTF-8 byte-order mark so the first header cell is clean.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $delimiter = static::detectDelimiter($contents);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = array_map(
                fn ($value) => is_string($value) ? trim($value) : $value,
                $row,
            );
        }

        fclose($handle);

        return $rows;
    }

    protected static function detectDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n") ?: '';

        $candidates = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($candidates);
        $best = array_key_first($candidates);

        return ($candidates[$best] ?? 0) > 0 ? $best : ',';
    }
}
