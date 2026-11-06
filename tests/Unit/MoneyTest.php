<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function formats(): array
    {
        return [
            'zero' => [0, '৳0'],
            'under a thousand' => [99900, '৳999'],
            'thousand' => [100000, '৳1,000'],
            'fifty thousand' => [5_000_000, '৳50,000'],
            'lakh grouping' => [19_500_000, '৳1,95,000'],
            'ten lakh' => [100_000_000, '৳10,00,000'],
            'crore' => [1_234_567_800, '৳1,23,45,678'],
            'paisa shown only when not zero' => [45_050, '৳450.50'],
            'single paisa' => [105, '৳1.05'],
            'negative uses the minus sign' => [-320_000, '−৳3,200'],
        ];
    }

    #[DataProvider('formats')]
    public function test_it_formats_poisha_with_indian_grouping(int $poisha, string $expected): void
    {
        $this->assertSame($expected, Money::format($poisha));
    }

    public function test_it_can_show_an_explicit_plus_sign(): void
    {
        $this->assertSame('+৳55,000', Money::format(5_500_000, withSign: true));
        $this->assertSame('৳0', Money::format(0, withSign: true));
    }

    public function test_hidden_amounts_never_show_a_number(): void
    {
        $this->assertSame('৳ ••••', Money::format(123_456_789, hidden: true));
    }

    /**
     * @return array<string, array{string|null, int|null}>
     */
    public static function inputs(): array
    {
        return [
            'plain' => ['1200', 120_000],
            'grouped' => ['1,20,000', 12_000_000],
            'with symbol and space' => ['৳ 2,000', 200_000],
            'decimal' => ['450.5', 45_050],
            'two decimals' => ['0.99', 99],
            'empty' => ['', null],
            'null' => [null, null],
            'letters' => ['abc', null],
            'negative' => ['-5', null],
            'three decimals' => ['1.234', null],
            'too large' => ['999999999999999', null],
        ];
    }

    #[DataProvider('inputs')]
    public function test_it_reads_what_a_person_typed(?string $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    public function test_it_gives_editable_text_for_a_field(): void
    {
        $this->assertSame('45000', Money::toInput(4_500_000));
        $this->assertSame('450.50', Money::toInput(45_050));
        $this->assertSame('45,000', Money::toGroupedInput(4_500_000));
    }

    public function test_it_rounds_to_the_nearest_taka(): void
    {
        $this->assertSame(131_800, Money::roundToTaka(131_785.7));
        $this->assertSame(62_900, Money::roundToTaka(62_857));
    }
}
