<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\ValueObject;

use InvalidArgumentException;

final readonly class AdjustmentComment
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new InvalidArgumentException('An adjustment comment is required.');
        }

        $this->value = $normalized;
    }
}
