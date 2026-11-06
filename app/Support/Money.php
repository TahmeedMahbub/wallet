<?php

namespace App\Support;

/**
 * Money is stored as integer poisha (1 taka = 100 poisha) and shown in taka
 * with Indian digit grouping and English numerals, e.g. ৳1,95,000.
 */
final class Money
{
    public const SYMBOL = '৳';

    public const MINUS = '−';

    /**
     * Largest amount accepted from a form: ৳999,99,99,999.99 (fits BIGINT with room to sum).
     */
    public const MAX_POISHA = 99_999_999_999_99;

    /**
     * Format poisha for display: ৳1,95,000 (paisa only when not zero).
     */
    public static function format(int $poisha, bool $withSign = false, bool $hidden = false): string
    {
        if ($hidden) {
            return self::SYMBOL.' ••••';
        }

        $sign = $poisha < 0 ? self::MINUS : ($withSign && $poisha > 0 ? '+' : '');
        $abs = abs($poisha);
        $taka = intdiv($abs, 100);
        $paisa = $abs % 100;

        return $sign.self::SYMBOL.self::group($taka).($paisa > 0 ? '.'.str_pad((string) $paisa, 2, '0', STR_PAD_LEFT) : '');
    }

    /**
     * Group whole taka the Indian way: 1,95,000.
     */
    public static function group(int $taka): string
    {
        $digits = (string) abs($taka);

        if (strlen($digits) <= 3) {
            return $digits;
        }

        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $rest = strrev(implode(',', str_split(strrev($rest), 2)));

        return $rest.','.$last3;
    }

    /**
     * Read what a person typed ("1,200", "৳ 1200.5") as poisha, or null when it is not a valid amount.
     */
    public static function parse(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }

        $clean = str_replace([',', ' ', self::SYMBOL, "\u{00A0}"], '', trim($input));

        if ($clean === '' || ! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        [$taka, $fraction] = array_pad(explode('.', $clean, 2), 2, '0');
        $poisha = ((int) $taka) * 100 + (int) str_pad($fraction, 2, '0');

        return $poisha > self::MAX_POISHA ? null : $poisha;
    }

    /**
     * Plain editable text for an amount field: 45000 or 450.50 (no grouping).
     */
    public static function toInput(int $poisha): string
    {
        $taka = intdiv(abs($poisha), 100);
        $paisa = abs($poisha) % 100;

        return (string) $taka.($paisa > 0 ? '.'.str_pad((string) $paisa, 2, '0', STR_PAD_LEFT) : '');
    }

    /**
     * Grouped number for an amount field while typing: 45,000.
     */
    public static function toGroupedInput(int $poisha): string
    {
        $paisa = abs($poisha) % 100;

        return self::group(intdiv(abs($poisha), 100)).($paisa > 0 ? '.'.str_pad((string) $paisa, 2, '0', STR_PAD_LEFT) : '');
    }

    /**
     * Round poisha to the nearest whole taka (still in poisha).
     */
    public static function roundToTaka(float|int $poisha): int
    {
        return (int) (round($poisha / 100) * 100);
    }
}
