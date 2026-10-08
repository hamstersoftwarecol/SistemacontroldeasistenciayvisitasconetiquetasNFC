<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Exporta tablas a CSV (compatible con Excel en español) o a XLSX sin dependencias externas.
 */
class SpreadsheetExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel reconozca UTF-8
            fputcsv($out, $headers, ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($value) => self::csvSafe(self::scalar($value)), $row), ';', '"', '');
            }

            fclose($out);
        }, Str::finish($filename, '.csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, array{headers: list<string>, rows: iterable<array<int, mixed>>}>  $sheets
     */
    public static function xlsx(string $filename, array $sheets): BinaryFileResponse
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión zip de PHP es necesaria para exportar a Excel.');
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $names = [];
        $index = 0;
        foreach ($sheets as $name => $sheet) {
            $index++;
            $names[$index] = self::sheetName((string) $name, $index);
            $zip->addFromString("xl/worksheets/sheet{$index}.xml", self::sheetXml($sheet['headers'], $sheet['rows']));
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($names)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbook($names));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels(count($names)));
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->close();

        return response()->download($path, Str::finish($filename, '.xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Evita que Excel interprete como fórmula un texto escrito por usuarios (inyección CSV).
     */
    private static function csvSafe(string|int|float $value): string|int|float
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private static function scalar(mixed $value): string|int|float
    {
        return match (true) {
            is_bool($value) => $value ? 'Sí' : 'No',
            $value === null => '',
            is_int($value), is_float($value) => $value,
            default => (string) $value,
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    private static function sheetXml(array $headers, iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>';

        foreach ($headers as $i => $header) {
            $width = max(10, min(45, mb_strlen($header) + 6));
            $col = $i + 1;
            $xml .= "<col min=\"{$col}\" max=\"{$col}\" width=\"{$width}\" customWidth=\"1\"/>";
        }

        $xml .= '</cols><sheetData>'.self::row(1, $headers, true);

        $r = 1;
        foreach ($rows as $row) {
            $xml .= self::row(++$r, array_values($row));
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private static function row(int $number, array $values, bool $header = false): string
    {
        $xml = "<row r=\"{$number}\">";

        foreach ($values as $i => $value) {
            $ref = self::column($i).$number;
            $value = self::scalar($value);
            $style = $header ? ' s="1"' : '';

            if (is_int($value) || is_float($value)) {
                $xml .= "<c r=\"{$ref}\"{$style}><v>{$value}</v></c>";
            } else {
                $text = htmlspecialchars(self::clean($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">{$text}</t></is></c>";
            }
        }

        return $xml.'</row>';
    }

    private static function clean(string $value): string
    {
        // Elimina caracteres de control no válidos en XML.
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
    }

    private static function column(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - $mod, 26);
        }

        return $name;
    }

    private static function sheetName(string $name, int $index): string
    {
        $name = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name));

        return mb_substr($name !== '' ? $name : "Hoja {$index}", 0, 31);
    }

    private static function contentTypes(int $count): string
    {
        $sheets = '';
        for ($i = 1; $i <= $count; $i++) {
            $sheets .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets
            .'</Types>';
    }

    /**
     * @param  array<int, string>  $names
     */
    private static function workbook(array $names): string
    {
        $sheets = '';
        foreach ($names as $i => $name) {
            $escaped = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $sheets .= "<sheet name=\"{$escaped}\" sheetId=\"{$i}\" r:id=\"rId{$i}\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            ."<sheets>{$sheets}</sheets></workbook>";
    }

    private static function workbookRels(int $count): string
    {
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
        }
        $styles = $count + 1;
        $rels .= "<Relationship Id=\"rId{$styles}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>";

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF4F46E5"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
