<?php namespace Renick\Survey\Classes\Export;

use RuntimeException;

/**
 * One worksheet of the XlsxWriter; rows are streamed to a temporary file.
 */
class XlsxSheet
{
    public string $name;

    /** @var resource|null */
    protected $handle;

    protected string $file;

    protected int $row = 0;

    protected bool $finished = false;

    /**
     * @param float[] $columnWidths
     */
    public function __construct(string $name, array $columnWidths = [])
    {
        $this->name = $name;
        $file = tempnam(sys_get_temp_dir(), 'srvx');
        $handle = $file !== false ? fopen($file, 'wb') : false;
        if ($handle === false) {
            throw new RuntimeException('Cannot create temporary file');
        }
        $this->file = $file;
        $this->handle = $handle;

        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>';
        if ($columnWidths) {
            $head .= '<cols>';
            foreach (array_values($columnWidths) as $i => $width) {
                $head .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$width . '" customWidth="1"/>';
            }
            $head .= '</cols>';
        }
        fwrite($this->handle, $head . '<sheetData>');
    }

    /**
     * Appends a row. Numbers become numeric cells, everything else text (sanitized).
     *
     * @param array $cells
     * @param int $style default style for all cells
     * @param array<int,int> $columnStyles per column (0-based) style override
     */
    public function addRow(array $cells, int $style = XlsxWriter::STYLE_DEFAULT, array $columnStyles = []): void
    {
        $this->row++;
        $xml = '<row r="' . $this->row . '">';

        foreach (array_values($cells) as $i => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $s = $columnStyles[$i] ?? $style;
            $attr = $s ? ' s="' . $s . '"' : '';
            $ref = self::columnName($i) . $this->row;

            if (is_int($value) || (is_float($value) && is_finite($value))) {
                $xml .= '<c r="' . $ref . '"' . $attr . '><v>' . $value . '</v></c>';
            } else {
                $text = htmlspecialchars(CellSanitizer::text((string)(is_bool($value) ? (int)$value : $value)), ENT_XML1 | ENT_NOQUOTES, 'UTF-8');
                $xml .= '<c r="' . $ref . '"' . $attr . ' t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
            }
        }

        fwrite($this->handle, $xml . '</row>');
    }

    /**
     * Closes the sheet and returns the temp file path.
     */
    public function finish(): string
    {
        if (!$this->finished) {
            fwrite($this->handle, '</sheetData></worksheet>');
            fclose($this->handle);
            $this->handle = null;
            $this->finished = true;
        }

        return $this->file;
    }

    public function cleanup(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
        }
        if (is_file($this->file)) {
            @unlink($this->file);
        }
    }

    /**
     * 0 => A, 25 => Z, 26 => AA ...
     */
    public static function columnName(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26) . $name;
        }

        return $name;
    }
}
