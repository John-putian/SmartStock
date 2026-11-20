<?php
/**
 * Minimal, dependency-free .xlsx writer.
 *
 * WHY THIS EXISTS
 * ---------------
 * The reports used to come out as CSV. A CSV is only text: it carries no
 * column widths, no number formats, no header. Excel therefore opened the
 * sales export with the date column too narrow to show a date - the famous
 * row of ######## - and every peso figure as a bare number. Nothing can be
 * done to the CSV to fix that, because the CSV has nowhere to put the answer.
 *
 * An .xlsx is a ZIP of XML parts, so it can carry all of it. The obvious way
 * to build one is PHP's ZipArchive, but the zip extension is NOT enabled in
 * this XAMPP install, and PhpSpreadsheet would need Composer. So the ZIP
 * container is written here by hand - about forty lines of pack() - and the
 * export then depends on nothing but PHP itself and travels with the project
 * to any machine.
 */
class Xlsx
{
    /* Style slots, matching the cellXfs order in styles() below. */
    const S_DEFAULT = 0;
    const S_TITLE   = 1;
    const S_SUBTITLE= 2;
    const S_HEADER  = 3;
    const S_TEXT    = 4;
    const S_DATETIME= 5;
    const S_DATE    = 6;
    const S_MONEY   = 7;
    const S_INT     = 8;
    const S_TOT_TXT = 9;
    const S_TOT_MONEY=10;
    const S_TOT_INT = 11;

    /**
     * Build and send a workbook as a download.
     *
     * @param string $filename  e.g. "AA_sales_2026-09-01_to_2026-10-02.xlsx"
     * @param array  $spec [
     *     'title'    => string,            // big heading on row 1
     *     'subtitle' => string,            // smaller line on row 2
     *     'sheet'    => string,            // tab name
     *     'columns'  => [ ['label'=>..,'type'=>'text|money|int|datetime|date','width'=>int], ... ],
     *     'rows'     => [ [v,v,v], ... ],
     *     'totals'   => [ 'Label', null, 'sum', ... ]   // optional: 'sum' totals that column
     * ]
     */
    public static function send(string $filename, array $spec): void
    {
        $xlsx = self::build($spec);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: max-age=0');
        echo $xlsx;
        exit;
    }

    public static function build(array $spec): string
    {
        $cols  = $spec['columns'] ?? [];
        $rows  = $spec['rows'] ?? [];
        $title = (string) ($spec['title'] ?? '');
        $sub   = (string) ($spec['subtitle'] ?? '');
        $sheet = substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', (string) ($spec['sheet'] ?? 'Report')), 0, 31) ?: 'Report';
        $n     = count($cols);

        // Row layout: 1 title, 2 subtitle, 3 blank, 4 headers, 5.. data.
        $headerRow = 4;
        $firstData = 5;

        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $lastCol = self::colName(max(1, $n));
        $lastRow = $firstData + count($rows) - 1 + (isset($spec['totals']) ? 1 : 0);
        $xml .= '<dimension ref="A1:' . $lastCol . max($lastRow, 1) . '"/>';

        // Freeze everything above the first data row, so the headings stay put
        // however far down the sheet somebody scrolls.
        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
              . '<pane ySplit="' . $headerRow . '" topLeftCell="A' . $firstData
              . '" activePane="bottomLeft" state="frozen"/>'
              . '</sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';

        $xml .= '<cols>';
        foreach ($cols as $i => $c) {
            $w = (int) ($c['width'] ?? self::defaultWidth($c['type'] ?? 'text'));
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        if ($title !== '') {
            $xml .= '<row r="1" ht="20" customHeight="1">'
                  . self::cell('A1', self::S_TITLE, $title, 'text') . '</row>';
        }
        if ($sub !== '') {
            $xml .= '<row r="2">' . self::cell('A2', self::S_SUBTITLE, $sub, 'text') . '</row>';
        }

        $xml .= '<row r="' . $headerRow . '" ht="22" customHeight="1">';
        foreach ($cols as $i => $c) {
            $xml .= self::cell(self::colName($i + 1) . $headerRow, self::S_HEADER,
                               (string) ($c['label'] ?? ''), 'text');
        }
        $xml .= '</row>';

        $r = $firstData;
        foreach ($rows as $row) {
            $row = array_values($row);
            $xml .= '<row r="' . $r . '">';
            foreach ($cols as $i => $c) {
                $type = (string) ($c['type'] ?? 'text');
                $xml .= self::cell(self::colName($i + 1) . $r, self::styleFor($type),
                                   $row[$i] ?? null, $type);
            }
            $xml .= '</row>';
            $r++;
        }

        if (!empty($spec['totals'])) {
            $xml .= '<row r="' . $r . '">';
            foreach ($cols as $i => $c) {
                $t    = $spec['totals'][$i] ?? null;
                $type = (string) ($c['type'] ?? 'text');
                if ($t === 'sum') {
                    $sum = 0.0;
                    foreach ($rows as $row) { $sum += (float) (array_values($row)[$i] ?? 0); }
                    $style = $type === 'int' ? self::S_TOT_INT : self::S_TOT_MONEY;
                    $xml .= self::cell(self::colName($i + 1) . $r, $style, $sum,
                                       $type === 'int' ? 'int' : 'money');
                } else {
                    $xml .= self::cell(self::colName($i + 1) . $r, self::S_TOT_TXT,
                                       $t === null ? '' : (string) $t, 'text');
                }
            }
            $xml .= '</row>';
            $r++;
        }

        $xml .= '</sheetData>';
        if ($n > 0 && $rows) {
            $xml .= '<autoFilter ref="A' . $headerRow . ':' . $lastCol . ($r - 1) . '"/>';
        }
        $xml .= '</worksheet>';

        return self::zip([
            '[Content_Types].xml'      => self::contentTypes(),
            '_rels/.rels'              => self::rootRels(),
            'xl/workbook.xml'          => self::workbook($sheet),
            'xl/_rels/workbook.xml.rels' => self::workbookRels(),
            'xl/styles.xml'            => self::styles(),
            'xl/worksheets/sheet1.xml' => $xml,
        ]);
    }

    /* ----------------------------------------------------------- cells */

    private static function styleFor(string $type): int
    {
        switch ($type) {
            case 'money':    return self::S_MONEY;
            case 'int':      return self::S_INT;
            case 'datetime': return self::S_DATETIME;
            case 'date':     return self::S_DATE;
            default:         return self::S_TEXT;
        }
    }

    private static function defaultWidth(string $type): int
    {
        switch ($type) {
            case 'money':    return 14;
            case 'int':      return 9;
            case 'datetime': return 19;   // wide enough that a date is never ########
            case 'date':     return 12;
            default:         return 20;
        }
    }

    private static function cell(string $ref, int $style, $value, string $type): string
    {
        $s = ' s="' . $style . '"';

        if ($value === null || $value === '') {
            return '<c r="' . $ref . '"' . $s . '/>';
        }

        if ($type === 'datetime' || $type === 'date') {
            $serial = self::excelDate((string) $value);
            if ($serial === null) {   // not a parseable date - keep the raw text
                return '<c r="' . $ref . '" s="' . self::S_TEXT . '" t="inlineStr"><is><t>'
                     . self::esc((string) $value) . '</t></is></c>';
            }
            return '<c r="' . $ref . '"' . $s . '><v>' . rtrim(rtrim(number_format($serial, 8, '.', ''), '0'), '.') . '</v></c>';
        }

        if ($type === 'money' || $type === 'int') {
            if (!is_numeric($value)) {   // e.g. the "—" placeholder in a numeric column
                return '<c r="' . $ref . '" s="' . self::S_TEXT . '" t="inlineStr"><is><t>'
                     . self::esc((string) $value) . '</t></is></c>';
            }
            return '<c r="' . $ref . '"' . $s . '><v>' . (0 + $value) . '</v></c>';
        }

        return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">'
             . self::esc((string) $value) . '</t></is></c>';
    }

    /**
     * Excel counts days from 1899-12-30. Both sides are built as naive local
     * datetimes so no timezone can shift a sale onto the wrong day.
     */
    private static function excelDate(string $v): ?float
    {
        $v = trim($v);
        if ($v === '' || $v === '0000-00-00' || strpos($v, '0000-00-00') === 0) return null;
        try {
            $d = new DateTimeImmutable($v);
        } catch (Throwable $e) {
            return null;
        }
        $epoch = new DateTimeImmutable('1899-12-30 00:00:00');
        $days  = (int) $epoch->diff($d->setTime(0, 0, 0))->format('%r%a');
        $frac  = ((int) $d->format('H') * 3600 + (int) $d->format('i') * 60 + (int) $d->format('s')) / 86400;
        return $days + $frac;
    }

    private static function esc(string $s): string
    {
        // Strip control characters Excel refuses to open, then escape.
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function colName(int $n): string
    {
        $s = '';
        while ($n > 0) { $m = ($n - 1) % 26; $s = chr(65 + $m) . $s; $n = (int) (($n - $m) / 26); }
        return $s ?: 'A';
    }

    /* ------------------------------------------------------- xml parts */

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
          . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
          . '<Default Extension="xml" ContentType="application/xml"/>'
          . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
          . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
          . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
          . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
          . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
          . '</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
          . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
          . '<sheets><sheet name="' . self::esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
          . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
          . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
          . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
          . '</Relationships>';
    }

    private static function styles(): string
    {
        // numFmt 164 datetime, 165 money, 166 integer, 167 date
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
          . '<numFmts count="4">'
          .   '<numFmt numFmtId="164" formatCode="yyyy&quot;-&quot;mm&quot;-&quot;dd\ hh:mm"/>'
          .   '<numFmt numFmtId="165" formatCode="#,##0.00"/>'
          .   '<numFmt numFmtId="166" formatCode="#,##0"/>'
          .   '<numFmt numFmtId="167" formatCode="yyyy&quot;-&quot;mm&quot;-&quot;dd"/>'
          . '</numFmts>'
          . '<fonts count="5">'
          .   '<font><sz val="11"/><name val="Calibri"/></font>'
          .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
          .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
          .   '<font><b/><sz val="14"/><color rgb="FF8A0C0E"/><name val="Calibri"/></font>'
          .   '<font><sz val="10"/><color rgb="FF7A726A"/><name val="Calibri"/></font>'
          . '</fonts>'
          . '<fills count="3">'
          .   '<fill><patternFill patternType="none"/></fill>'
          .   '<fill><patternFill patternType="gray125"/></fill>'
          .   '<fill><patternFill patternType="solid"><fgColor rgb="FFC41114"/><bgColor indexed="64"/></patternFill></fill>'
          . '</fills>'
          . '<borders count="2">'
          .   '<border><left/><right/><top/><bottom/><diagonal/></border>'
          .   '<border><left/><right/><top style="thin"><color rgb="FF999999"/></top><bottom/><diagonal/></border>'
          . '</borders>'
          . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
          . '<cellXfs count="12">'
          .   '<xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0"/>'                                   // 0 default
          .   '<xf numFmtId="0"   fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                      // 1 title
          .   '<xf numFmtId="0"   fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                      // 2 subtitle
          .   '<xf numFmtId="0"   fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' // 3 header
          .   '<xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top"/></xf>' // 4 text
          .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'              // 5 datetime
          .   '<xf numFmtId="167" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'              // 6 date
          .   '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'              // 7 money
          .   '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'              // 8 int
          .   '<xf numFmtId="0"   fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'      // 9 total text
          .   '<xf numFmtId="165" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyNumberFormat="1"/>' // 10 total money
          .   '<xf numFmtId="166" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyNumberFormat="1"/>' // 11 total int
          . '</cellXfs>'
          . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
          . '</styleSheet>';
    }

    /* ------------------------------------------------------------- zip */

    /**
     * A ZIP container, written by hand because the zip extension is not
     * enabled here. Entries are deflated when zlib is available and stored
     * uncompressed otherwise; both are valid and Excel reads either.
     */
    private static function zip(array $files): string
    {
        $now     = getdate();
        $dosTime = ($now['hours'] << 11) | ($now['minutes'] << 5) | (int) ($now['seconds'] / 2);
        $dosDate = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];

        $local = ''; $central = ''; $offset = 0;

        foreach ($files as $name => $data) {
            $crc   = crc32($data);
            $usize = strlen($data);

            $method = 0; $payload = $data;
            if (function_exists('gzdeflate')) {
                $def = gzdeflate($data, 6);
                if ($def !== false) { $method = 8; $payload = $def; }
            }
            $csize = strlen($payload);

            $entry = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $method, $dosTime, $dosDate,
                          $crc, $csize, $usize, strlen($name), 0) . $name . $payload;
            $local .= $entry;

            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method,
                             $dosTime, $dosDate, $crc, $csize, $usize,
                             strlen($name), 0, 0, 0, 0, 32, $offset) . $name;

            $offset += strlen($entry);
        }

        return $local . $central
             . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files),
                    strlen($central), $offset, 0);
    }
}
