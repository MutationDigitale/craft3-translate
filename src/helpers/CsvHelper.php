<?php

namespace mutation\translate\helpers;

/**
 * Protects the CSV files against formula injection (https://owasp.org/www-community/attacks/CSV_Injection).
 *
 * @internal
 */
class CsvHelper
{
    /**
     * The values starting with one of these characters are run as formulas by spreadsheet apps, also after apostrophes
     * (e.g. `'=1+1`), so the exported values can be imported back as they were.
     */
    private const FORMULA_PATTERN = "/^'*[=+\\-@\t\r]/";

    /**
     * Returns a value to export: an apostrophe is added before the values that would be run as formulas (e.g.
     * `=HYPERLINK("http://example.com","x")` becomes `'=HYPERLINK("http://example.com","x")`), so they're shown as text.
     */
    public static function escapeFormula(?string $value): ?string
    {
        return $value !== null && preg_match(self::FORMULA_PATTERN, $value) ? "'" . $value : $value;
    }

    /**
     * Returns an imported value: the apostrophe added by {@see escapeFormula()} is removed.
     */
    public static function unescapeFormula(?string $value): ?string
    {
        return $value !== null && str_starts_with($value, "'") && preg_match(self::FORMULA_PATTERN, substr($value, 1))
            ? substr($value, 1)
            : $value;
    }
}
