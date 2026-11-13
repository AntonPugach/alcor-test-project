<?php

declare(strict_types=1);

namespace Alcor\Payroll\Infrastructure;

use Alcor\Payroll\Application\Exception\ConcurrencyException;
use Alcor\Payroll\Application\Port\Outbound\PayrollEventStore;
use Alcor\Payroll\Domain\Event\PayrollEvent;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;
use InvalidArgumentException;

final class InMemoryPayrollEventStore implements PayrollEventStore
{
    /** @var array<string, list<PayrollEvent>> */
    private array $streams = [];

    /** @return list<PayrollEvent> */
    public function eventsFor(PayrollLineId $lineId): array
    {
        return $this->streams[$lineId->value] ?? [];
    }

    /** @param list<PayrollEvent> $events */
    public function append(PayrollLineId $lineId, array $events, int $expectedVersion): void
    {
        $stream        = $this->streams[$lineId->value] ?? [];
        $actualVersion = count($stream);
        if ($expectedVersion !== $actualVersion) {
            throw new ConcurrencyException($expectedVersion, $actualVersion);
        }

        if (array_any($events, fn(PayrollEvent $event) => ! $event->lineId()->equals($lineId))) {
            throw new InvalidArgumentException('Cannot append an event to another line stream.');
        }

        if ($events !== []) {
            $this->streams[$lineId->value] = [...$stream, ...$events];
        }
    }
}
