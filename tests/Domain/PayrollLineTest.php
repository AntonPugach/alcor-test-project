<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Domain;

use Alcor\Payroll\Domain\Event\ManualAdjustmentAdded;
use Alcor\Payroll\Domain\Event\SystemValueCalculated;
use Alcor\Payroll\Domain\PayrollLine;
use Alcor\Payroll\Domain\ValueObject\AdjustmentAmount;
use Alcor\Payroll\Domain\ValueObject\AdjustmentComment;
use Alcor\Payroll\Domain\ValueObject\PayrollLineId;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PayrollLineTest extends TestCase
{
    public function test_it_freezes_system_value_and_keeps_the_adjustment_history(): void
    {
        $id   = new PayrollLineId('salary-line');
        $line = PayrollLine::reconstitute($id, [
            new SystemValueCalculated($id, new AdjustmentAmount(105_000)),
        ]);
        $line->addAdjustment(-4_555, ' Declined dental benefit ');
        $line->recalculate(120_000);
        $line->addAdjustment(10_010, 'Missed overtime');

        self::assertSame(105_000, $line->systemValueCents());
        self::assertSame(110_455, $line->currentValueCents());
        self::assertSame('Declined dental benefit', $line->adjustments()[0]->comment->value);
        self::assertCount(2, $line->releaseEvents());
    }

    public function test_it_rejects_zero_value_adjustments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdjustmentAmount(0);
    }

    public function test_empty_and_whitespace_only_comments_are_rejected(): void
    {
        foreach (['', " \t\n "] as $comment) {
            try {
                new AdjustmentComment($comment);
                self::fail('Expected an empty comment to be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_replay_rejects_an_adjustment_before_the_system_value(): void
    {
        $id = new PayrollLineId('salary-line');

        $this->expectException(DomainException::class);
        PayrollLine::reconstitute($id, [
            new ManualAdjustmentAdded($id, new AdjustmentAmount(100), new AdjustmentComment('Correction')),
        ]);
    }

    public function test_replay_rejects_events_for_another_payroll_line(): void
    {
        $lineId      = new PayrollLineId('salary-line');
        $otherLineId = new PayrollLineId('bonus-line');

        $this->expectException(InvalidArgumentException::class);
        PayrollLine::reconstitute($lineId, [
            new SystemValueCalculated($otherLineId, new AdjustmentAmount(1_000)),
        ]);
    }

    public function test_recalculation_disabled_generates_no_new_events(): void
    {
        $id   = new PayrollLineId('salary-line');
        $line = PayrollLine::reconstitute($id, [
            new SystemValueCalculated($id, new AdjustmentAmount(1_000)),
            new ManualAdjustmentAdded($id, new AdjustmentAmount(100), new AdjustmentComment('Correction')),
        ]);

        $line->recalculate(2_000);

        self::assertSame([], $line->releaseEvents());
        self::assertSame(1_100, $line->currentValueCents());
    }
}
