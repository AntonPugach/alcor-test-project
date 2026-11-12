<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application;

use Alcor\Payroll\Application\Port\Inbound\PayrollLineUseCases;
use Alcor\Payroll\Application\Port\Outbound\PayrollEventStore;
use Alcor\Payroll\Application\ReadModel\AdjustmentSnapshot;
use Alcor\Payroll\Application\ReadModel\PayrollLineSnapshot;
use Alcor\Payroll\Domain\PayrollLine;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;

final readonly class PayrollLineService implements PayrollLineUseCases
{
    public function __construct(private PayrollEventStore $eventStore)
    {
    }

    public function recalculate(PayrollLineId $lineId, int $amountCents): void
    {
        [$line, $version] = $this->load($lineId);
        $line->recalculate($amountCents);
        $this->saveChanges($lineId, $line, $version);
    }

    public function addAdjustment(
        PayrollLineId $lineId,
        int $amountCents,
        string $comment,
    ): void {
        [$line, $version] = $this->load($lineId);
        $line->addAdjustment($amountCents, $comment);
        $this->saveChanges($lineId, $line, $version);
    }

    public function getLine(PayrollLineId $lineId): PayrollLineSnapshot
    {
        [$line] = $this->load($lineId);

        $adjustments = array_map(
            static fn($adjustment): AdjustmentSnapshot => new AdjustmentSnapshot(
                $adjustment->amount->cents,
                $adjustment->comment->value,
            ),
            $line->adjustments(),
        );

        return new PayrollLineSnapshot(
            $lineId->value,
            $line->systemValueCents(),
            $line->currentValueCents(),
            $adjustments,
        );
    }

    /** @return array{PayrollLine, int} */
    private function load(PayrollLineId $lineId): array
    {
        $history = $this->eventStore->eventsFor($lineId);

        return [PayrollLine::reconstitute($lineId, $history), count($history)];
    }

    private function saveChanges(PayrollLineId $lineId, PayrollLine $line, int $expectedVersion): void
    {
        $events = $line->releaseEvents();
        if ($events === []) {
            return;
        }

        $this->eventStore->append($lineId, $events, $expectedVersion);
    }
}
