<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Application;

use Alcor\Payroll\Application\Exception\ConcurrencyException;
use Alcor\Payroll\Application\PayrollLineService;
use Alcor\Payroll\Application\Port\Outbound\PayrollEventStore;
use Alcor\Payroll\Domain\Event\ManualAdjustmentAdded;
use Alcor\Payroll\Domain\Event\SystemValueCalculated;
use Alcor\Payroll\Domain\ValueObject\AdjustmentAmount;
use Alcor\Payroll\Domain\ValueObject\AdjustmentComment;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;
use Alcor\Payroll\Infrastructure\InMemoryPayrollEventStore;
use PHPUnit\Framework\TestCase;

final class PayrollLineServiceTest extends TestCase
{
    public function test_it_passes_the_loaded_stream_version_and_writes_on_match(): void
    {
        $store   = new InMemoryPayrollEventStore();
        $service = new PayrollLineService($store);
        $id      = new PayrollLineId('salary-line');
        $service->recalculate($id, 1_000);

        $service->addAdjustment($id, 100, 'Correction');

        self::assertSame(1_100, $service->getLine($id)->currentValueCents);
        self::assertCount(2, $store->eventsFor($id));
    }

    public function test_it_preserves_the_assignment_example_result(): void
    {
        $service = new PayrollLineService(new InMemoryPayrollEventStore());
        $id      = new PayrollLineId('salary-line');
        $service->recalculate($id, 100_000);
        $service->recalculate($id, 105_000);
        $service->addAdjustment($id, -4_555, 'Declined dental benefit');
        $service->recalculate($id, 120_000);
        $service->addAdjustment($id, 10_010, 'Missed overtime bonus');
        $service->addAdjustment($id, -10, 'Minor rounding adjustment');
        $service->addAdjustment($id, -20, 'Second rounding adjustment');
        $service->addAdjustment($id, 20, 'Correcting adjustment #4');

        $line = $service->getLine($id);

        self::assertSame(105_000, $line->systemValueCents);
        self::assertSame(110_445, $line->currentValueCents);
        self::assertSame([-4_555, 10_010, -10, -20, 20], array_map(
            static fn($adjustment): int => $adjustment->amountCents,
            $line->adjustments,
        ));
    }

    public function test_ignored_recalculation_does_not_persist_an_empty_event_set(): void
    {
        $store   = new AppendCountingEventStore();
        $service = new PayrollLineService($store);
        $id      = new PayrollLineId('salary-line');
        $service->recalculate($id, 1_000);
        $service->addAdjustment($id, 100, 'Correction');
        $appendCallsAfterAdjustment = $store->appendCalls;

        $service->recalculate($id, 2_000);

        self::assertSame($appendCallsAfterAdjustment, $store->appendCalls);
        self::assertSame(1_100, $service->getLine($id)->currentValueCents);
    }

    public function test_conflict_preserves_the_winning_history_and_rejects_the_stale_write(): void
    {
        $id         = new PayrollLineId('salary-line');
        $innerStore = new InMemoryPayrollEventStore();
        $innerStore->append($id, [new SystemValueCalculated($id, new AdjustmentAmount(1_000))], 0);
        $store   = new ConcurrentAppendOnReadStore($innerStore, $id);
        $service = new PayrollLineService($store);

        try {
            $service->addAdjustment($id, 100, 'Stale correction');
            self::fail('Expected the stale append to conflict.');
        } catch (ConcurrencyException $exception) {
            self::assertSame(1, $exception->expectedVersion);
            self::assertSame(2, $exception->actualVersion);
        }

        $history = $innerStore->eventsFor($id);
        self::assertCount(2, $history);
        self::assertInstanceOf(ManualAdjustmentAdded::class, $history[1]);
        self::assertSame('Concurrent correction', $history[1]->comment->value);
    }
}

final class AppendCountingEventStore implements PayrollEventStore
{
    public int $appendCalls = 0;

    private InMemoryPayrollEventStore $inner;

    public function __construct()
    {
        $this->inner = new InMemoryPayrollEventStore();
    }

    public function eventsFor(PayrollLineId $lineId): array
    {
        return $this->inner->eventsFor($lineId);
    }

    public function append(PayrollLineId $lineId, array $events, int $expectedVersion): void
    {
        $this->appendCalls++;
        $this->inner->append($lineId, $events, $expectedVersion);
    }
}

final class ConcurrentAppendOnReadStore implements PayrollEventStore
{
    private bool $hasInjectedConcurrentAppend = false;

    public function __construct(
        private readonly InMemoryPayrollEventStore $inner,
        private readonly PayrollLineId $lineId,
    ) {
    }

    public function eventsFor(PayrollLineId $lineId): array
    {
        $history = $this->inner->eventsFor($lineId);
        if ($lineId->equals($this->lineId) && ! $this->hasInjectedConcurrentAppend) {
            $this->hasInjectedConcurrentAppend = true;
            $this->inner->append($lineId, [
                new ManualAdjustmentAdded($lineId, new AdjustmentAmount(50),
                    new AdjustmentComment('Concurrent correction')),
            ], count($history));
        }

        return $history;
    }

    public function append(PayrollLineId $lineId, array $events, int $expectedVersion): void
    {
        $this->inner->append($lineId, $events, $expectedVersion);
    }
}
