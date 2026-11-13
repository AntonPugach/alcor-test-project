# Payroll Line Adjustments

A small PHP domain model for the history of manual adjustments to an automatically calculated payroll line. It demonstrates the supplied assignment without a web UI or framework.

## Business rules

- A payroll line receives system-calculated values until a specialist adds the first manual adjustment.
- The system value in effect immediately before that first adjustment becomes the frozen base value. Later recalculations are ignored.
- Each adjustment is a non-zero signed amount in cents and requires a non-blank comment.
- Adjustments are appended as immutable events. A mistake is corrected by adding another adjustment; previous events are not edited or deleted.
- The current value is the frozen system value plus every adjustment, in event order.

Money uses integer cents to avoid floating-point arithmetic. System-calculated values use `MoneyAmount` and may be zero. Manual corrections use `AdjustmentAmount`, which rejects zero. The line must have a system value before it can be adjusted.

## Subject area and responsibility boundaries

The bounded context is **Manual Payroll Line Adjustments**. It owns the history and current value of one payroll line after the payroll calculation system supplies a base value. It does not calculate salaries, decide benefit eligibility, manage employees, or run payroll. Those are external subject areas and enter this model only through a calculated amount.

Within this context:

- **Payroll line:** `PayrollLine` owns the invariant that system recalculation stops after the first manual adjustment, the signed adjustment sequence, and the resulting value.
- **Adjustment audit:** `SystemValueCalculated` and `ManualAdjustmentAdded` are the append-only facts that explain the line's current state. A correction is another event; the model exposes no edit or delete operation.
- **Use case orchestration:** `PayrollLineService` loads a line's event history, asks the aggregate to make a change, and appends newly recorded events. It does not make payroll policy decisions.
- **Event persistence:** the `PayrollEventStore` outbound port defines the persistence capability the use case needs. The adapter owns storage mechanics, not domain rules.
- **Line read view:** `PayrollLineSnapshot` and `AdjustmentSnapshot` are application-facing output data. They expose current state and audit details without leaking the aggregate or event classes to delivery clients.

## Clean and Hexagonal Architecture

The inbound `PayrollLineUseCases` port is the API for driving the application. `PayrollLineService` implements it. The outbound `PayrollEventStore` port is the API the application uses to persist and load history. `InMemoryPayrollEventStore` is a replaceable adapter for that port, while `bin/demo.php` is a CLI driving adapter and the composition root.

Dependencies point inward: the CLI and infrastructure adapter depend on application contracts; application code depends on the domain; the domain depends only on PHP. The service receives its outbound port through constructor injection. The domain aggregate validates both new commands and replayed history, keeping business rules in one place. Read views are mapped at the application boundary so the delivery side does not depend on domain event types.

The `PayrollLineId`, `AdjustmentAmount`, and `AdjustmentComment` value objects protect domain inputs. The event-store append port takes an `expectedVersion`; the in-memory adapter checks the current event count and validates the full batch before changing the stream, so a version conflict or invalid event cannot cause a partial append. The application skips empty event batches. A production adapter must provide the same check-and-append guarantee across concurrent processes and durable storage. The event stream is the audit record; the read view is rebuilt by replaying it.

## Requirements

- PHP 8.5 or newer
- Composer

## Run

```sh
composer install
composer test
php bin/demo.php
```

The demo reproduces the supplied sequence. It prints a current value of `$1,104.45`, a frozen system value of `$1,050.00`, and all five adjustments, including the compensating correction.

## Tests

PHPUnit tests are organized under Domain, Application, and Infrastructure. They cover adjustment validation and replay, comment validation, event ownership, recalculation precedence, empty writes, stream isolation, expected-version writes, conflict handling, and preservation of history after a conflict.
