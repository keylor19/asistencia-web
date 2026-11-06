<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Genera un archivo de Excel (.xlsx) sencillo: varias hojas, primera fila en
 * negrita y números como números. Usa ZipArchive, sin paquetes extra.
 */
class XlsxWriter
{
    /** @var array<int, array{name: string, rows: array, widths: array}> */
    private array $sheets = [];

    public function addSheet(string $name, array $rows, array $widths = []): static
    {
        $this->sheets[] = ['name' => $this->sheetName($name), 'rows' => $rows, 'widths' => $widths];

        return $this;
    }

    /**
     * Guarda el libro en un archivo temporal y devuelve su ruta.
     */
    public function save(): string
    {
        if ($this->sheets === []) {
            $this->addSheet('Hoja1', []);
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', $this->worksheet($sheet));
        }

        $zip->close();

        return $path;
    }

    /**
     * Excel no acepta nombres de hoja repetidos, con : \ / ? * [ ] ni de más de 31 caracteres.
     */
    private function sheetName(string $name): string
    {
        $base = mb_substr(trim(str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $name)) ?: 'Hoja', 0, 31);
        $taken = array_map(fn ($s) => mb_strtolower($s['name']), $this->sheets);

        $candidate = $base;
        for ($i = 2; in_array(mb_strtolower($candidate), $taken, true); $i++) {
            $suffix = " ($i)";
            $candidate = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
        }

        return $candidate;
    }

    private function worksheet(array $sheet): string
    {
        $cols = '';
        foreach ($sheet['widths'] as $index => $width) {
            $n = $index + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $width . '" customWidth="1"/>';
        }

        $rows = '';
        foreach (array_values($sheet['rows']) as $r => $row) {
            $cells = '';
            foreach (array_values($row) as $c => $value) {
                $cells .= $this->cell($this->columnLetter($c) . ($r + 1), $value, $r === 0);
            }
            $rows .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $rows . '</sheetData>'
            . '</worksheet>';
    }

    private function cell(string $ref, mixed $value, bool $header): string
    {
        $style = $header ? ' s="1"' : '';

        if ($value === null || $value === '') {
            return '<c r="' . $ref . '"' . $style . '/>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
        }

        return '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">'
            . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            . '</t></is></c>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letter = chr(65 + ($index - 1) % 26) . $letter;
        }

        return $letter;
    }

    private function contentTypes(): string
    {
        $overrides = '';
        foreach ($this->sheets as $index => $sheet) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="' . htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" '
                . 'sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $index => $sheet) {
            $rels .= '<Relationship Id="rId' . ($index + 1) . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
        }

        $stylesId = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId' . $stylesId . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
