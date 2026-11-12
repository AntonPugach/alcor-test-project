<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\ValueObject;

use InvalidArgumentException;

final readonly class PayrollLineId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Payroll line id is required.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
