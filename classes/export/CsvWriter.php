<?php namespace Renick\Survey\Classes\Export;

use RuntimeException;

/**
 * Fallback writer (UTF-8 with BOM, semicolon separated) for servers without ZipArchive.
 * Offers the same API as XlsxWriter; multiple sheets are written one after another.
 */
class CsvWriter
{
    /** @var CsvSheet[] */
    protected array $sheets = [];

    public function addSheet(string $name, array $columnWidths = []): CsvSheet
    {
        return $this->sheets[] = new CsvSheet($name);
    }

    public function save(string $path): void
    {
        $out = fopen($path, 'wb');
        if ($out === false) {
            throw new RuntimeException('Cannot create csv file');
        }

        fwrite($out, "\xEF\xBB\xBF");
        foreach ($this->sheets as $i => $sheet) {
            if ($i > 0) {
                fwrite($out, "\r\n");
            }
            if (count($this->sheets) > 1) {
                fwrite($out, CsvSheet::line([$sheet->name]));
            }
            $in = $sheet->finish();
            $src = fopen($in, 'rb');
            if ($src !== false) {
                stream_copy_to_stream($src, $out);
                fclose($src);
            }
            $sheet->cleanup();
        }
        fclose($out);
    }
}
