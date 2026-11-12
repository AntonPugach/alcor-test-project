<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Port\Inbound;

use Alcor\Payroll\Application\ReadModel\PayrollLineSnapshot;
use Alcor\Payroll\Domain\ValueObject\AdjustmentAmount;
use Alcor\Payroll\Domain\ValueObject\AdjustmentComment;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;

interface PayrollLineUseCases
{
    public function recalculate(PayrollLineId $lineId, int $amountCents): void;

    public function addAdjustment(
        PayrollLineId $lineId,
        int $amountCents,
        string $comment,
    ): void;

    public function getLine(PayrollLineId $lineId): PayrollLineSnapshot;
}
