<?php namespace Renick\Survey\Classes\Export;

use RuntimeException;

class CsvSheet
{
    public string $name;

    /** @var resource|null */
    protected $handle;

    protected string $file;

    public function __construct(string $name)
    {
        $this->name = CellSanitizer::cleanXml($name);
        $file = tempnam(sys_get_temp_dir(), 'srvc');
        $handle = $file !== false ? fopen($file, 'wb') : false;
        if ($handle === false) {
            throw new RuntimeException('Cannot create temporary file');
        }
        $this->file = $file;
        $this->handle = $handle;
    }

    public function addRow(array $cells, int $style = XlsxWriter::STYLE_DEFAULT, array $columnStyles = []): void
    {
        $out = [];
        foreach (array_values($cells) as $i => $value) {
            $s = $columnStyles[$i] ?? $style;
            if (is_float($value) && $s === XlsxWriter::STYLE_PERCENT) {
                $out[] = number_format($value * 100, 2, '.', '') . '%';
            } elseif (is_int($value) || is_float($value)) {
                $out[] = (string)$value;
            } else {
                $out[] = CellSanitizer::text((string)($value ?? ''));
            }
        }

        fwrite($this->handle, self::line($out));
    }

    public static function line(array $values): string
    {
        $fields = array_map(static function (string $v): string {
            return preg_match('/[;"\r\n]/', $v) ? '"' . str_replace('"', '""', $v) . '"' : $v;
        }, array_map('strval', $values));

        return implode(';', $fields) . "\r\n";
    }

    public function finish(): string
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
        }

        return $this->file;
    }

    public function cleanup(): void
    {
        $this->finish();
        if (is_file($this->file)) {
            @unlink($this->file);
        }
    }
}
