<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

class RepaymentCalculator
{
    /**
     * Money is calculated in hundredths of MMK to avoid floating-point rounding errors.
     *
     * @return array{
     *     principal_minor: int,
     *     interest_minor: int,
     *     total_minor: int,
     *     regular_instalment_minor: int,
     *     instalments: list<array{number: int, due_date: string, amount_minor: int}>
     * }
     */
    public function calculate(
        string $amount,
        string $annualRate,
        int $months,
        string $startDate,
    ): array {
        if (PHP_INT_SIZE < 8) {
            throw new RuntimeException('This calculator requires 64-bit PHP.');
        }

        $principalMinor = $this->decimalToHundredths($amount);
        $rateHundredths = $this->decimalToHundredths($annualRate);

        if ($principalMinor < 10_000_000 || $principalMinor > 1_000_000_000) {
            throw new InvalidArgumentException('Amount must be 100,000–10,000,000 MMK.');
        }

        if ($months < 3 || $months > 24) {
            throw new InvalidArgumentException('Term must be 3–24 months.');
        }

        if ($rateHundredths > 99_999) {
            throw new InvalidArgumentException('Rate exceeds the existing database precision.');
        }

        $scheduleStart = CarbonImmutable::createFromFormat('!Y-m-d', $startDate);

        if ($scheduleStart === null || $scheduleStart->format('Y-m-d') !== $startDate) {
            throw new InvalidArgumentException('Use a valid YYYY-MM-DD start date.');
        }

        /** Flat interest = principal × annual rate × months / 12, rounded half up. */
        $interestDivisor = 12 * 100 * 100;
        $interestNumerator = $principalMinor * $rateHundredths * $months;

        $interestMinor = intdiv($interestNumerator + intdiv($interestDivisor, 2), $interestDivisor);
        $totalMinor = $principalMinor + $interestMinor;
        $regularMinor = intdiv($totalMinor + intdiv($months, 2), $months);

        $instalments = [];

        for ($number = 1; $number <= $months; $number++) {
            $instalmentMinor = $regularMinor;

            if ($number === $months) {
                $instalmentMinor = $totalMinor - ($regularMinor * ($months - 1));
            }

            $instalments[] = [
                'number' => $number,
                'due_date' => $scheduleStart->addMonthsNoOverflow($number)->toDateString(),
                'amount_minor' => $instalmentMinor,
            ];
        }

        return [
            'principal_minor' => $principalMinor,
            'interest_minor' => $interestMinor,
            'total_minor' => $totalMinor,
            'regular_instalment_minor' => $regularMinor,
            'instalments' => $instalments,
        ];
    }

    public function format(int $minor): string
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        return number_format(intdiv($minor, 100), 0, '.', ',')
            .'.'
            .str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function decimalToHundredths(string $value): int
    {
        if (! preg_match('/\A(\d{1,8})(?:\.(\d{1,2}))?\z/', $value, $parts)) {
            throw new InvalidArgumentException('Use a non-negative decimal with up to two decimal places.');
        }

        $fraction = str_pad($parts[2] ?? '', 2, '0', STR_PAD_RIGHT);

        return ((int) $parts[1] * 100) + (int) $fraction;
    }
}
