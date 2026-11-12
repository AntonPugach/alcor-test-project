<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Exception;

use RuntimeException;

final class ConcurrencyException extends RuntimeException
{
    public function __construct(
        public readonly int $expectedVersion,
        public readonly int $actualVersion,
    ) {
        parent::__construct(sprintf(
            'Event stream version conflict: expected %d, actual %d.',
            $expectedVersion,
            $actualVersion,
        ));
    }
}
