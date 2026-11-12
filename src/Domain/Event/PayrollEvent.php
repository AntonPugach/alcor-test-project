<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Event;

use Alcor\Payroll\Domain\ValueObject\PayrollLineId;

interface PayrollEvent
{
    public function lineId(): PayrollLineId;
}
