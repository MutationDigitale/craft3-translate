<?php

namespace mutation\translate\helpers;

/**
 * CSV helpers
 */
class CsvHelper
{
    public const BOM = "\xEF\xBB\xBF";

    private const FORMULA_CHARACTERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Prefixes values that spreadsheet apps would interpret as formulas with a single quote (CSV injection).
     *
     * The values that would look escaped (quotes followed by a formula character) get a quote too, so unescaping gives them back.
     */
    public static function escapeValue(?string $value): ?string
    {
        if ($value !== null && self::startsWithFormula(ltrim($value, "'"))) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Reverts {@see escapeValue()}.
     */
    public static function unescapeValue(?string $value): ?string
    {
        if ($value !== null && str_starts_with($value, "'") && self::startsWithFormula(ltrim($value, "'"))) {
            return substr($value, 1);
        }

        return $value;
    }

    private static function startsWithFormula(string $value): bool
    {
        return $value !== '' && in_array($value[0], self::FORMULA_CHARACTERS, true);
    }

    public static function removeBom(string $value): string
    {
        return str_starts_with($value, self::BOM) ? substr($value, strlen(self::BOM)) : $value;
    }
}
