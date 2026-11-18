<?php

namespace App\Support;

use ZipArchive;

/**
 * A designed Excel report (.xlsx), written without any library: a coloured title with the name
 * of the business, a block of details about it, a table with a heading band, borders, banded rows
 * and a total, set up to print on a landscape page. The font is Vazirmatn (Excel shows it when
 * the font is on the computer, and its own font otherwise: a workbook cannot carry a font).
 */
class XlsxReport
{
    public const FONT = 'Vazirmatn';

    // styles (cellXfs), in the order they are written below
    private const TITLE = 1, SUBTITLE = 2, LABEL = 3, VALUE = 4, HEAD = 5, TEXT = 6, NUM = 7, INT = 8, TOTAL = 9, TOTAL_NUM = 10, FOOT = 11, TEXT_BAND = 12, NUM_BAND = 13, INT_BAND = 14, TOTAL_INT = 15;

    /**
     * @param array{
     *   sheet: string, rtl?: bool, title: string, subtitle: string, info: list<array{0: string, 1: string}>,
     *   columns: list<array{0: string, 1?: string, 2?: int}>, rows: list<list<string|int|float|null>>,
     *   totals?: list<string|int|float|null>|null, footer?: string
     * } $doc  a column is [label, type (text, num, int), width]; a total cell that is null stays empty
     */
    public static function build(array $doc): string
    {
        $columns = $doc['columns'];
        $n = max(4, count($columns));
        $last = Xlsx::column($n - 1);
        $half = intdiv($n + 1, 2);
        $xml = '';
        $merges = [];
        $r = 0;

        $row = function (array $cells, ?float $height = null) use (&$xml, &$r) {
            $r++;
            $xml .= '<row r="'.$r.'"'.($height ? ' ht="'.$height.'" customHeight="1"' : '').'>'.implode('', $cells).'</row>';

            return $r;
        };
        // (the cell builders read the number of the row being written, so they take it by reference)
        $text = function (int $c, string $value, int $style) use (&$r) {
            return '<c r="'.Xlsx::column($c).($r + 1).'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::escape($value).'</t></is></c>';
        };
        $blank = function (int $c, int $style) use (&$r) {
            return '<c r="'.Xlsx::column($c).($r + 1).'" s="'.$style.'"/>';
        };
        $number = function (int $c, string|int|float $value, int $style) use (&$r) {
            return '<c r="'.Xlsx::column($c).($r + 1).'" s="'.$style.'"><v>'.$value.'</v></c>';
        };
        $span = function (int $from, int $to, string $value, int $style) use ($text, $blank) {
            $cells = [$text($from, $value, $style)];
            for ($c = $from + 1; $c <= $to; $c++) {
                $cells[] = $blank($c, $style);
            }

            return $cells;
        };

        // the name of the business, and what the file is
        $merges[] = 'A'.($r + 1).':'.$last.($r + 1);
        $row($span(0, $n - 1, $doc['title'], self::TITLE), 34);
        $merges[] = 'A'.($r + 1).':'.$last.($r + 1);
        $row($span(0, $n - 1, $doc['subtitle'], self::SUBTITLE), 24);
        $row([], 6);

        // the details, two to a row: a label and its value, twice
        foreach (array_chunk($doc['info'], 2) as $pair) {
            $cells = [];
            foreach ($pair as $i => [$label, $value]) {
                $start = $i === 0 ? 0 : $half;
                $end = $i === 0 ? $half - 1 : $n - 1;
                $cells[] = $text($start, $label, self::LABEL);
                array_push($cells, ...$span($start + 1, $end, (string) $value, self::VALUE));
                if ($end > $start + 1) {
                    $merges[] = Xlsx::column($start + 1).($r + 1).':'.Xlsx::column($end).($r + 1);
                }
            }
            $row($cells, 20);
        }
        $row([], 8);

        // the table
        $cells = [];
        foreach ($columns as $c => $column) {
            $cells[] = $text($c, $column[0], self::HEAD);
        }
        $head = $row($cells, 30);
        $widths = array_map(fn (array $column) => max($column[2] ?? 0, mb_strlen($column[0]) + 4), $columns);

        foreach ($doc['rows'] as $i => $line) {
            $band = $i % 2 === 1;
            $cells = [];
            foreach ($columns as $c => $column) {
                $value = $line[$c] ?? null;
                $type = $column[1] ?? 'text';
                if ($type === 'num' && $value !== null && $value !== '') {
                    $cells[] = $number($c, Num::money($value), $band ? self::NUM_BAND : self::NUM);
                    $widths[$c] = max($widths[$c], strlen((string) $value) + 6);
                } elseif ($type === 'int' && $value !== null && $value !== '') {
                    $cells[] = $number($c, (int) $value, $band ? self::INT_BAND : self::INT);
                } elseif ((string) $value === '') {
                    $cells[] = $blank($c, $band ? self::TEXT_BAND : self::TEXT);
                } else {
                    $cells[] = $text($c, (string) $value, $band ? self::TEXT_BAND : self::TEXT);
                    $widths[$c] = max($widths[$c], min(46, mb_strlen((string) $value) + 3));
                }
            }
            $row($cells, 19);
        }
        if (! $doc['rows']) {
            $merges[] = 'A'.($r + 1).':'.$last.($r + 1);
            $row($span(0, $n - 1, '—', self::TEXT), 22);
        }
        $end = $r;

        if (! empty($doc['totals'])) {
            $cells = [];
            foreach ($columns as $c => $column) {
                $value = $doc['totals'][$c] ?? null;
                $type = $column[1] ?? 'text';
                $cells[] = $value === null || $value === '' ? $blank($c, self::TOTAL)
                    : ($type === 'num' ? $number($c, Num::money($value), self::TOTAL_NUM)
                        : (is_int($value) ? $number($c, $value, self::TOTAL_INT) : $text($c, (string) $value, self::TOTAL)));
                if ($type === 'num' && $value !== null) {
                    $widths[$c] = max($widths[$c], strlen((string) $value) + 6);
                }
            }
            $row($cells, 22);
        }
        if (! empty($doc['footer'])) {
            $row([]);
            $merges[] = 'A'.($r + 1).':'.$last.($r + 1);
            $row($span(0, $n - 1, $doc['footer'], self::FOOT));
        }

        $cols = '';
        for ($c = 0; $c < $n; $c++) {
            $cols .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.min(48, max(11, $widths[$c] ?? 12)).'" customWidth="1"/>';
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"'.(! empty($doc['rtl']) ? ' rightToLeft="1"' : '').'>'
            .'<pane ySplit="'.$head.'" topLeftCell="A'.($head + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols>'.$cols.'</cols><sheetData>'.$xml.'</sheetData>'
            .($doc['rows'] ? '<autoFilter ref="A'.$head.':'.Xlsx::column(count($columns) - 1).$end.'"/>' : '')
            .'<mergeCells count="'.count($merges).'">'.implode('', array_map(fn (string $ref) => '<mergeCell ref="'.$ref.'"/>', $merges)).'</mergeCells>'
            .'<printOptions horizontalCentered="1"/><pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'<headerFooter><oddFooter>&amp;C&amp;P / &amp;N</oddFooter></headerFooter></worksheet>';

        $name = Xlsx::sheetName($doc['sheet']);
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::escape($name).'" sheetId="1" r:id="rId1"/></sheets>'
                // the heading of the table is printed again on every page
                .'<definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\''.str_replace("'", "''", self::escape($name)).'\'!$'.$head.':$'.$head.'</definedName></definedNames></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => self::styles(),
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $file => $content) {
            $zip->addFromString($file, $content);
        }
        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private static function styles(): string
    {
        $font = fn (int $size, bool $bold = false, ?string $color = null, bool $italic = false) => '<font>'.($bold ? '<b/>' : '').($italic ? '<i/>' : '').'<sz val="'.$size.'"/>'
            .($color ? '<color rgb="FF'.$color.'"/>' : '').'<name val="'.self::FONT.'"/></font>';
        $fill = fn (string $rgb) => '<fill><patternFill patternType="solid"><fgColor rgb="FF'.$rgb.'"/><bgColor indexed="64"/></patternFill></fill>';
        $side = fn (string $name) => '<'.$name.' style="thin"><color rgb="FFD5DBE6"/></'.$name.'>';
        $xf = fn (int $font, int $fill, int $border, int $fmt = 0, string $align = '') => '<xf numFmtId="'.$fmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1"'
            .($fmt ? ' applyNumberFormat="1"' : '').' applyAlignment="1"><alignment vertical="center"'.$align.'/></xf>';
        $center = ' horizontal="center" wrapText="1"';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            // fonts: 0 text, 1 bold, 2 title, 3 subtitle, 4 heading of the table, 5 small grey
            .'<fonts count="6">'.$font(10).$font(10, true, '1F2937').$font(16, true, 'FFFFFF').$font(11, false, 'DCE6F8').$font(10, true, 'FFFFFF').$font(9, false, '6B7280', true).'</fonts>'
            // fills: 2 navy, 3 blue, 4 band, 5 total, 6 label
            .'<fills count="7"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'.$fill('1F3A8A').$fill('2A78D6').$fill('F5F8FC').$fill('E3EBF8').$fill('EEF2F8').'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>'.$side('left').$side('right').$side('top').$side('bottom').'<diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="16">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .$xf(2, 2, 0, 0, $center).$xf(3, 2, 0, 0, $center)                  // 1 title, 2 subtitle
            .$xf(1, 6, 1).$xf(0, 0, 1, 0, ' wrapText="1"')                      // 3 label, 4 value
            .$xf(4, 3, 1, 0, $center)                                            // 5 heading
            .$xf(0, 0, 1).$xf(0, 0, 1, 164).$xf(0, 0, 1, 3)                      // 6 text, 7 number, 8 whole number
            .$xf(1, 5, 1).$xf(1, 5, 1, 164)                                      // 9 total, 10 total number
            .$xf(5, 0, 0)                                                        // 11 foot
            .$xf(0, 4, 1).$xf(0, 4, 1, 164).$xf(0, 4, 1, 3)                      // 12-14 the same on a banded row
            .$xf(1, 5, 1, 3)                                                     // 15 total whole number
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
