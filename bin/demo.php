<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$autoload    = $projectRoot.'/vendor/autoload.php';
require is_file($autoload) ? $autoload : $projectRoot.'/tests/bootstrap.php';

use Alcor\Payroll\Application\PayrollLineService;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;
use Alcor\Payroll\Infrastructure\InMemoryPayrollEventStore;

$service = new PayrollLineService(new InMemoryPayrollEventStore());
$lineId  = new PayrollLineId('employee-42:base-salary');
$service->recalculate($lineId, 100_000);
$service->recalculate($lineId, 105_000);
$service->addAdjustment($lineId, -4_555, 'Employee declined dental benefit; reversing deduction');
$service->recalculate($lineId, 120_000); // Ignored after the first adjustment.
$service->addAdjustment($lineId, 10_010, 'Late correction: missed approved overtime bonus');
$service->addAdjustment($lineId, -10, 'Minor rounding adjustment');
$service->addAdjustment($lineId, -20, 'Second minor rounding adjustment');
$service->addAdjustment($lineId, 20, 'Correcting mistake in adjustment #4');

$line = $service->getLine($lineId);
printf("Current value: $%s\n", number_format($line->currentValueCents / 100, 2));
printf("Frozen system value: $%s\n", number_format($line->systemValueCents / 100, 2));
foreach ($line->adjustments as $index => $adjustment) {
    printf("Adjustment %d: %+.2f — %s\n", $index + 1, $adjustment->amountCents / 100, $adjustment->comment);
}
