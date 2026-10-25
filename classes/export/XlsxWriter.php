<?php namespace Renick\Survey\Classes\Export;

use RuntimeException;
use ZipArchive;

/**
 * Minimal streaming .xlsx writer (ZipArchive + inline strings), no external dependencies.
 *
 * Row data is streamed into temporary files, so memory use stays flat for large result sets.
 * Usage: $w = new XlsxWriter(); $s = $w->addSheet('Name', [20, 30]); $s->addRow([...]); $w->save($path);
 */
class XlsxWriter
{
    /** Cell styles (index into cellXfs of styles.xml). */
    public const STYLE_DEFAULT = 0;
    public const STYLE_HEADER = 1;
    public const STYLE_PERCENT = 2;

    /** @var XlsxSheet[] */
    protected array $sheets = [];

    public static function isAvailable(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * @param string $name sheet title (invalid characters are replaced, max 31 chars)
     * @param float[] $columnWidths widths in characters
     */
    public function addSheet(string $name, array $columnWidths = []): XlsxSheet
    {
        $name = trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', CellSanitizer::cleanXml($name)) ?? '');
        $name = mb_substr($name !== '' ? $name : 'Sheet' . (count($this->sheets) + 1), 0, 31, 'UTF-8');

        return $this->sheets[] = new XlsxSheet($name, $columnWidths);
    }

    public function save(string $path): void
    {
        if (!self::isAvailable()) {
            throw new RuntimeException('ZipArchive is not available');
        }
        if (!$this->sheets) {
            $this->addSheet('Sheet1');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create xlsx file');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRels());
            $zip->addFromString('xl/workbook.xml', $this->workbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
            $zip->addFromString('xl/styles.xml', $this->styles());

            foreach ($this->sheets as $i => $sheet) {
                $zip->addFile($sheet->finish(), 'xl/worksheets/sheet' . ($i + 1) . '.xml');
            }

            // all files must still exist while the archive is written
            if (!$zip->close()) {
                throw new RuntimeException('Cannot write xlsx file');
            }
        } finally {
            foreach ($this->sheets as $sheet) {
                $sheet->cleanup();
            }
        }
    }

    protected const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    protected function contentTypes(): string
    {
        $o = self::XML . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->sheets as $i => $_) {
            $o .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $o . '</Types>';
    }

    protected function rootRels(): string
    {
        return self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected function workbook(): string
    {
        $o = self::XML . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($this->sheets as $i => $sheet) {
            $o .= '<sheet name="' . htmlspecialchars($sheet->name, ENT_QUOTES | ENT_XML1, 'UTF-8') . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }

        return $o . '</sheets></workbook>';
    }

    protected function workbookRels(): string
    {
        $o = self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $_) {
            $o .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $n = count($this->sheets) + 1;

        return $o . '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    protected function styles(): string
    {
        return self::XML . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
