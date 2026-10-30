<?php

namespace mutation\translate\tests\unit;

use mutation\translate\helpers\CsvHelper;

class CsvHelperTest extends TestCase
{
    /**
     * @dataProvider escapeProvider
     */
    public function testEscapeRoundTrip(?string $value, ?string $escaped): void
    {
        $this->assertSame($escaped, CsvHelper::escapeValue($value));
        $this->assertSame($value, CsvHelper::unescapeValue($escaped));
    }

    public static function escapeProvider(): array
    {
        return [
            'formula' => ['=SUM(A1:A2)', "'=SUM(A1:A2)"],
            'plus' => ['+1', "'+1"],
            'minus' => ['-5 % off', "'-5 % off"],
            'at' => ['@home', "'@home"],
            'tab' => ["\tindented", "'\tindented"],
            'carriage return' => ["\rtext", "'\rtext"],
            'regular text' => ['Hello', 'Hello'],
            'formula character inside' => ['a = b', 'a = b'],
            'quote not followed by a formula character' => ["'quoted'", "'quoted'"],
            'looks escaped' => ["'=x", "''=x"],
            'quotes followed by a formula character' => ["''+1", "'''+1"],
            'quote only' => ["'", "'"],
            'empty' => ['', ''],
            'null' => [null, null],
        ];
    }

    public function testUnescapeOnlyRemovesTheEscapingQuote(): void
    {
        $this->assertSame("'", CsvHelper::unescapeValue("'"));
        $this->assertSame("'=x", CsvHelper::unescapeValue("''=x"));
        $this->assertSame("''", CsvHelper::unescapeValue("''"));
    }

    public function testRemoveBom(): void
    {
        $this->assertSame('Key', CsvHelper::removeBom(CsvHelper::BOM . 'Key'));
        $this->assertSame('Key', CsvHelper::removeBom('Key'));
    }
}
