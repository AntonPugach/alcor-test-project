<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Infrastructure;

use Alcor\Payroll\Application\Exception\ConcurrencyException;
use Alcor\Payroll\Domain\Event\PayrollEvent;
use Alcor\Payroll\Domain\Event\SystemValueCalculated;
use Alcor\Payroll\Domain\ValueObject\MoneyAmount;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;
use Alcor\Payroll\Infrastructure\InMemoryPayrollEventStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InMemoryPayrollEventStoreTest extends TestCase
{
    public function test_append_succeeds_when_expected_version_matches(): void
    {
        $store = new InMemoryPayrollEventStore();
        $id    = new PayrollLineId('salary-line');

        $store->append($id, [new SystemValueCalculated($id, new MoneyAmount(1_000))], 0);

        self::assertCount(1, $store->eventsFor($id));
    }

    public function test_mismatched_version_throws_without_changing_history(): void
    {
        $store = new InMemoryPayrollEventStore();
        $id    = new PayrollLineId('salary-line');
        $store->append($id, [new SystemValueCalculated($id, new MoneyAmount(1_000))], 0);

        try {
            $store->append($id, [new SystemValueCalculated($id, new MoneyAmount(2_000))], 0);
            self::fail('Expected a concurrency conflict.');
        } catch (ConcurrencyException $exception) {
            self::assertSame(0, $exception->expectedVersion);
            self::assertSame(1, $exception->actualVersion);
        }

        self::assertSame(1_000, $store->eventsFor($id)[0]->amount->cents);
    }

    public function test_invalid_batch_append_does_not_partially_write(): void
    {
        $store       = new InMemoryPayrollEventStore();
        $lineId      = new PayrollLineId('salary-line');
        $otherLineId = new PayrollLineId('bonus-line');
        $store->append($lineId, [new SystemValueCalculated($lineId, new MoneyAmount(1_000))], 0);

        $this->expectException(InvalidArgumentException::class);
        try {
            $store->append($lineId, [
                new SystemValueCalculated($lineId, new MoneyAmount(1_100)),
                new SystemValueCalculated($otherLineId, new MoneyAmount(2_000)),
            ], 1);
        } finally {
            self::assertCount(1, $store->eventsFor($lineId));
            self::assertSame(1_000, $store->eventsFor($lineId)[0]->amount->cents);
        }
    }

    public function test_each_payroll_line_has_an_isolated_event_stream(): void
    {
        $store    = new InMemoryPayrollEventStore();
        $firstId  = new PayrollLineId('salary-line');
        $secondId = new PayrollLineId('bonus-line');
        $store->append($firstId, [new SystemValueCalculated($firstId, new MoneyAmount(1_000))], 0);
        $store->append($secondId, [new SystemValueCalculated($secondId, new MoneyAmount(500))], 0);

        self::assertSame(1_000, $store->eventsFor($firstId)[0]->amount->cents);
        self::assertSame(500, $store->eventsFor($secondId)[0]->amount->cents);
    }

    public function test_empty_append_does_not_create_or_extend_a_stream(): void
    {
        $store = new InMemoryPayrollEventStore();
        $id    = new PayrollLineId('salary-line');

        $store->append($id, [], 0);

        self::assertSame([], $store->eventsFor($id));
    }
}
