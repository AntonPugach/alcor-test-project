<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\ValueObject;

final readonly class MoneyAmount
{
    public function __construct(public int $cents)
    {
    }
}
