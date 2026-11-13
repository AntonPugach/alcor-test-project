<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Infrastructure;

use Alcor\Payroll\Application\Exception\ConcurrencyException;
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
        $store->append($id, [
            new SystemValueCalculated($id, new MoneyAmount(1_100)),
            new SystemValueCalculated($id, new MoneyAmount(1_200)),
        ], 1);

        try {
            $store->append($id, [
                new SystemValueCalculated($id, new MoneyAmount(2_000)),
                new SystemValueCalculated($id, new MoneyAmount(2_100)),
            ], 0);
            self::fail('Expected a concurrency conflict.');
        } catch (ConcurrencyException $exception) {
            self::assertSame(0, $exception->expectedVersion);
            self::assertSame(3, $exception->actualVersion);
        }

        $history = $store->eventsFor($id);
        self::assertCount(3, $history);
        self::assertSame([1_000, 1_100, 1_200], array_map(
            static fn(SystemValueCalculated $event): int => $event->amount->cents,
            $history,
        ));
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

    public function test_empty_append_does_not_create_a_stream(): void
    {
        $eventStore = new InMemoryPayrollEventStore();
        $limeId = new PayrollLineId('payroll-line-1');

        // Empty append to a non-existing stream.
        $eventStore->append($limeId, [], 0);

        // The stream must still be considered new.
        // A subsequent append with expected version 0 must succeed.
        $eventStore->append($limeId, [new SystemValueCalculated($limeId, new MoneyAmount(100_000))], 0);

        $storedEvents = $eventStore->eventsFor($limeId);
        self::assertCount(1, $storedEvents);
        self::assertSame(100_000, $storedEvents[0]->amount->cents);
    }

    public function test_empty_append_does_not_change_existing_stream_version(): void
    {
        $eventStore = new InMemoryPayrollEventStore();
        $limeId = new PayrollLineId('payroll-line-1');

        $eventStore->append($limeId, [new SystemValueCalculated($limeId, new MoneyAmount(100_000))], 0);

        // Empty append must not increment the stream version.
        $eventStore->append($limeId, [], 1);

        // Version must still be 1, so expected version 1 is valid.
        $eventStore->append($limeId, [new SystemValueCalculated($limeId, new MoneyAmount(105_000))], 1);

        $storedEvents = $eventStore->eventsFor($limeId);
        self::assertCount(2, $storedEvents);
        self::assertSame(100_000, $storedEvents[0]->amount->cents);
        self::assertSame(105_000, $storedEvents[1]->amount->cents);
    }
}
