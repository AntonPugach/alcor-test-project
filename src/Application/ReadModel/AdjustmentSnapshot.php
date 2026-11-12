<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\ReadModel;

final readonly class AdjustmentSnapshot
{
    public function __construct(
        public int $amountCents,
        public string $comment,
    ) {
    }
}
