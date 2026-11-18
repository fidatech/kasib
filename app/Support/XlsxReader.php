<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Reads the first sheet of an Excel file (.xlsx) or a CSV file into rows of text, without any
 * library. Only what an import needs: the values of the cells, as text.
 */
class XlsxReader
{
    /** @return array<int, array<int, string>> rows of cells, the empty ones included, so columns keep their place */
    public static function rows(string $path, string $extension): array
    {
        return strtolower($extension) === 'csv' ? self::csv($path) : self::xlsx($path);
    }

    private static function csv(string $path): array
    {
        $text = (string) file_get_contents($path);
        // Excel writes a mark in front of a UTF-8 file
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $delimiter = substr_count(strtok($text, "\n") ?: '', ';') > substr_count(strtok($text, "\n") ?: '', ',') ? ';' : ',';
        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $text);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = array_map(fn ($cell) => trim((string) $cell), $row);
        }
        fclose($handle);

        return $rows;
    }

    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The file is not an Excel workbook.');
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (self::xml($xml)->si as $item) {
                $shared[] = self::text($item);
            }
        }
        // the first sheet of the workbook
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        for ($i = 0; $sheet === false && $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/[^/]+\.xml$#', (string) $name)) {
                $sheet = $zip->getFromIndex($i);
            }
        }
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('The workbook has no sheet.');
        }

        $rows = [];
        foreach (self::xml($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $column = self::column((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = match ($type) {
                    's' => $shared[(int) $cell->v] ?? '',
                    'inlineStr' => self::text($cell->is),
                    default => (string) $cell->v,
                };
                $cells[$column] = trim($value);
            }
            if ($cells) {
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $column => $value) {
                    $line[$column] = $value;
                }
                $rows[] = $line;
            }
        }

        return $rows;
    }

    private static function xml(string $xml): \SimpleXMLElement
    {
        $doc = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        if ($doc === false) {
            throw new RuntimeException('The workbook cannot be read.');
        }

        return $doc;
    }

    /** The text of a cell that is written in pieces. */
    private static function text(?\SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        $text = isset($node->t) ? (string) $node->t : '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /** "C7" is column 2 (the first column is 0). */
    private static function column(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference));
        $n = 0;
        foreach (str_split($letters ?: 'A') as $letter) {
            $n = $n * 26 + (ord($letter) - 64);
        }

        return $n - 1;
    }
}
