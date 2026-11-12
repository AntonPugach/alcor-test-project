<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Port\Outbound;

use Alcor\Payroll\Domain\Event\PayrollEvent;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;

interface PayrollEventStore
{
    /** @return list<PayrollEvent> */
    public function eventsFor(PayrollLineId $lineId): array;

    /** @param  list<PayrollEvent>  $events */
    public function append(PayrollLineId $lineId, array $events, int $expectedVersion): void;
}
