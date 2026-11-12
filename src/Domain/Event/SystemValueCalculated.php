<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Event;

use Alcor\Payroll\Domain\ValueObject\AdjustmentAmount;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;

final readonly class SystemValueCalculated implements PayrollEvent
{
    public function __construct(
        public PayrollLineId $payrollLineId,
        public AdjustmentAmount $amount,
    ) {
    }

    public function lineId(): PayrollLineId
    {
        return $this->payrollLineId;
    }
}
