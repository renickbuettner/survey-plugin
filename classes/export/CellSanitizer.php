<?php namespace Renick\Survey\Classes\Export;

/**
 * Cleans cell values for export files (invalid XML characters, spreadsheet formula injection).
 */
final class CellSanitizer
{
    /** Maximum number of characters Excel accepts in a single cell. */
    public const MAX_CELL_LENGTH = 32767;

    /**
     * Strips characters that are invalid in XML 1.0 and repairs broken UTF-8.
     */
    public static function cleanXml(string $value): string
    {
        if (function_exists('mb_scrub')) {
            $value = mb_scrub($value, 'UTF-8');
        }

        $clean = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return $clean ?? '';
    }

    /**
     * Neutralizes values that a spreadsheet application could interpret as a formula
     * (CSV / Excel injection) by prefixing them with an apostrophe.
     */
    public static function neutralizeFormula(string $value): string
    {
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Full text cleanup used for string cells.
     */
    public static function text(string $value): string
    {
        $value = self::neutralizeFormula(self::cleanXml($value));

        if (mb_strlen($value, 'UTF-8') > self::MAX_CELL_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_CELL_LENGTH, 'UTF-8');
        }

        return $value;
    }
}
