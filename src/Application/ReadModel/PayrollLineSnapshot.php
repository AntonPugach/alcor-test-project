<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\ReadModel;

final readonly class PayrollLineSnapshot
{
    /** @param  list<AdjustmentSnapshot>  $adjustments */
    public function __construct(
        public string $lineId,
        public int $systemValueCents,
        public int $currentValueCents,
        public array $adjustments,
    ) {
    }
}
