<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\ValueObject;

use InvalidArgumentException;

final readonly class AdjustmentAmount
{
    public function __construct(public int $cents)
    {
        if ($cents === 0) {
            throw new InvalidArgumentException('An adjustment amount cannot be zero.');
        }
    }
}
