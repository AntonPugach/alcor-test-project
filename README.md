# Payroll Line Adjustments

This PHP project models the history of manual corrections to an automatically calculated payroll line. It includes a command-line demo and PHPUnit tests, but no web interface or framework.

## Rules

- The system can recalculate a line until its first manual adjustment.
- That adjustment freezes the system value as the line's base. Later system recalculations are ignored.
- Manual adjustments are signed, non-zero amounts in cents and require a comment.
- Adjustments are append-only. A mistake is fixed by adding a compensating adjustment; earlier events are retained.
- The current value is the frozen system value plus the adjustments in order.

System calculations use `MoneyAmount`, which permits zero. Manual adjustments use `AdjustmentAmount`, which rejects zero. `AdjustmentComment` trims whitespace and rejects comments that are empty after trimming.

## Scope and code boundaries

The project owns one payroll line's calculated value, manual adjustment history, and current value. Salary calculation, employee records, benefit eligibility, and payroll runs are outside its scope; the model receives the system-calculated amount from elsewhere.

`PayrollLine` enforces the rules and rebuilds its state by replaying events. `PayrollLineService` implements the inbound `PayrollLineUseCases` port: it loads a line's history, performs an operation, and appends only the events created by that operation. It uses the outbound `PayrollEventStore` port and returns `PayrollLineSnapshot` and `AdjustmentSnapshot` read data. `bin/demo.php` is the CLI adapter and composition root.

`InMemoryPayrollEventStore` is the storage adapter. It checks the expected version against the stream's event count and validates the complete batch before replacing the stream with the appended history. A stale version or a mismatched line ID leaves the history unchanged. The service skips appends when an operation produces no events.

The in-memory store is temporary: its data disappears when the process exits, and separate processes do not share its version checks. A production store would need durable storage and an atomic version check and append shared by all writers.

## Requirements and commands

The Composer constraint is PHP `^8.5` (PHP 8.5 or a later PHP 8.x release) and Composer.

```sh
composer install
composer test
composer example
```

The demo follows the assignment example. It recalculates the system value to `$1,050.00`, then applies five manual corrections. It prints the frozen system value, each adjustment and comment, and the expected final value of `$1,104.45`.

The PHPUnit suite covers the Domain, Application, and Infrastructure behavior, including event replay and optimistic concurrency conflicts.
