<?php

use App\Services\RepaymentCalculator;

test('invalid repayment start dates are rejected', function (string $startDate) {
    expect(fn () => (new RepaymentCalculator)->calculate('100000.00', '10.00', 12, $startDate))
        ->toThrow(InvalidArgumentException::class);
})->with(['2026-02-30', '2026-13-01', 'not-a-date']);

test('the brief example and final rounding adjustment', function () {
    $calculator = new RepaymentCalculator;
    $result = $calculator->calculate('1000000.00', '10.00', 12, '2026-01-31');

    $this->assertSame(10_000_000, $result['interest_minor']);
    $this->assertSame(110_000_000, $result['total_minor']);
    $this->assertCount(12, $result['instalments']);
    $this->assertSame(9_166_667, $result['instalments'][0]['amount_minor']);
    $this->assertSame(9_166_663, $result['instalments'][11]['amount_minor']);
    $this->assertSame(
        $result['total_minor'],
        array_sum(array_column($result['instalments'], 'amount_minor')),
    );
    $this->assertSame('91,666.63', $calculator->format($result['instalments'][11]['amount_minor']));
});

test('due dates stay anchored to the original day', function () {
    $calculator = new RepaymentCalculator;

    $ordinary = $calculator->calculate('100000.00', '10.00', 3, '2026-01-31');
    $this->assertSame('2026-02-28', $ordinary['instalments'][0]['due_date']);
    $this->assertSame('2026-03-31', $ordinary['instalments'][1]['due_date']);

    $leapYear = $calculator->calculate('100000.00', '10.00', 3, '2028-01-31');
    $this->assertSame('2028-02-29', $leapYear['instalments'][0]['due_date']);
});

test('zero rate still reconciles the final instalment', function () {
    $result = (new RepaymentCalculator)->calculate('100000.00', '0.00', 3, '2026-01-01');

    $this->assertSame(0, $result['interest_minor']);
    $this->assertSame(3_333_333, $result['instalments'][0]['amount_minor']);
    $this->assertSame(3_333_334, $result['instalments'][2]['amount_minor']);
    $this->assertSame(10_000_000, array_sum(array_column($result['instalments'], 'amount_minor')));
});

test('half a minor unit of interest rounds up', function () {
    $result = (new RepaymentCalculator)->calculate('100000.20', '10.00', 3, '2026-01-01');

    // Exact interest is 2,500.005 MMK, rounded to 2,500.01.
    $this->assertSame(250_001, $result['interest_minor']);
});

test('maximum supported values remain exact', function () {
    $result = (new RepaymentCalculator)->calculate('10000000.00', '999.99', 24, '2026-01-01');

    $this->assertSame(19_999_800_000, $result['interest_minor']);
    $this->assertSame(20_999_800_000, $result['total_minor']);
    $this->assertSame(
        $result['total_minor'],
        array_sum(array_column($result['instalments'], 'amount_minor')),
    );
});

test('invalid inputs are rejected', function (string $amount, string $rate, int $months) {
    $this->expectException(InvalidArgumentException::class);

    (new RepaymentCalculator)->calculate($amount, $rate, $months, '2026-01-01');
})->with([
    ['99999.99', '10.00', 12],
    ['10000000.01', '10.00', 12],
    ['100000.001', '10.00', 12],
    ['100000.00', '-1.00', 12],
    ['100000.00', '1000.00', 12],
    ['100000.00', '10.00', 2],
    ['100000.00', '10.00', 25],
]);
