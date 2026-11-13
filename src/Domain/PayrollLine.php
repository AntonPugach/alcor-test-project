<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain;

use Alcor\Payroll\Domain\Event\ManualAdjustmentAdded;
use Alcor\Payroll\Domain\Event\PayrollEvent;
use Alcor\Payroll\Domain\Event\SystemValueCalculated;
use Alcor\Payroll\Domain\ValueObject\AdjustmentAmount;
use Alcor\Payroll\Domain\ValueObject\AdjustmentComment;
use Alcor\Payroll\Domain\ValueObject\MoneyAmount;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;
use DomainException;
use InvalidArgumentException;

final class PayrollLine
{
    /** @var list<ManualAdjustmentAdded> */
    private array $adjustments = [];

    /** @var list<PayrollEvent> */
    private array $recordedEvents   = [];
    private ?int  $systemValueCents = null;

    private function __construct(
        private readonly PayrollLineId $id,
    ) {
    }

    public static function start(PayrollLineId $id): self
    {
        return new self($id);
    }

    /** @param list<PayrollEvent> $history */
    public static function reconstitute(PayrollLineId $id, array $history): self
    {
        $line = self::start($id);
        foreach ($history as $event) {
            $line->assertEventBelongsToThisLine($event);
            $line->apply($event);
        }

        return $line;
    }

    public function recalculate(int $amountCents): void
    {
        if (! $this->canRecalculateAutomatically()) {
            return;
        }

        $amount = new MoneyAmount($amountCents);
        $this->record(new SystemValueCalculated($this->id, $amount));
    }

    public function addAdjustment(int $amount, string $comment): void
    {
        $this->requireSystemValue();

        $amount  = new AdjustmentAmount($amount);
        $comment = new AdjustmentComment($comment);
        $event   = new ManualAdjustmentAdded($this->id, $amount, $comment);

        $this->applyAdjustment($event);
        $this->recordedEvents[] = $event;
    }

    public function currentValueCents(): int
    {
        $this->requireSystemValue();

        return array_reduce(
            $this->adjustments,
            static fn(int $total, ManualAdjustmentAdded $adjustment): int => $total + $adjustment->amount->cents,
            $this->systemValueCents,
        );
    }

    public function systemValueCents(): int
    {
        $this->requireSystemValue();

        return $this->systemValueCents;
    }

    /** @return list<ManualAdjustmentAdded> */
    public function adjustments(): array
    {
        return $this->adjustments;
    }

    /** @return list<PayrollEvent> */
    public function releaseEvents(): array
    {
        $events               = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function record(PayrollEvent $event): void
    {
        $this->apply($event);
        $this->recordedEvents[] = $event;
    }

    private function apply(PayrollEvent $event): void
    {
        if ($event instanceof SystemValueCalculated) {
            if ($this->canRecalculateAutomatically()) {
                $this->systemValueCents = $event->amount->cents;
            }

            return;
        }

        if ($event instanceof ManualAdjustmentAdded) {
            if (! $this->hasSystemValue()) {
                throw new DomainException('A manual adjustment cannot precede the first system calculation.');
            }

            $this->applyAdjustment($event);
            return;
        }

        throw new InvalidArgumentException('Unsupported payroll event.');
    }

    private function applyAdjustment(
        ManualAdjustmentAdded $event,
    ): void {
        $this->requireSystemValue();
        $this->adjustments[] = $event;
    }

    private function requireSystemValue(): void
    {
        if (! $this->hasSystemValue()) {
            throw new DomainException('Payroll line has no calculated value yet.');
        }
    }

    private function assertEventBelongsToThisLine(PayrollEvent $event): void
    {
        if (! $event->lineId()->equals($this->id)) {
            throw new InvalidArgumentException('Event belongs to a different payroll line.');
        }
    }

    private function hasSystemValue(): bool
    {
        return $this->systemValueCents !== null;
    }

    private function hasManualAdjustments(): bool
    {
        return $this->adjustments !== [];
    }

    private function canRecalculateAutomatically(): bool
    {
        return ! $this->hasManualAdjustments();
    }
}
