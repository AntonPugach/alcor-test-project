# Payroll Line Adjustments

A small PHP 8.5 project demonstrating an event-driven domain model for payroll line adjustments. It uses a lightweight layered architecture, an append-only event history, and optimistic concurrency checks.

The project is intentionally kept small. It includes a CLI example and PHPUnit tests, but no framework, web interface, or external infrastructure.

## Project Structure

```text
src/
  Domain/
    Event/
    ValueObject/
    PayrollLine.php
  Application/
    Exception/
    Port/
      Inbound/
      Outbound/
    ReadModel/
    PayrollLineService.php
  Infrastructure/
    InMemoryPayrollEventStore.php
bin/
  demo.php
tests/
  Application/
  Domain/
  Infrastructure/
```

### Domain

`PayrollLine` is the aggregate responsible for enforcing domain invariants and rebuilding its state through event replay.

Domain events represent system calculations and manual adjustments. Value Objects encapsulate amounts, comments, and payroll line identifiers.

### Application

`PayrollLineService` coordinates commands and the read use case. It loads the event history, reconstructs the aggregate, executes an operation, and persists newly recorded events.

The application layer depends on inbound and outbound ports rather than concrete infrastructure.

### Infrastructure

`InMemoryPayrollEventStore` implements the event storage contract. `bin/demo.php` acts as the CLI entry point and composition root, wiring the required dependencies manually.

## Design Decisions and Trade-offs

The implementation deliberately favors simplicity over production infrastructure.

- **In-memory storage.** The event store keeps data in PHP arrays, so history is lost when the process exits. Its optimistic concurrency checks work within a single instance only. A production implementation would require durable storage and an atomic version check and append.
- **Integer arithmetic.** Money is represented in cents to avoid floating-point calculations. Explicit overflow checks and business-level amount limits are not implemented; values are assumed to fit within the PHP integer range.
- **Minimal event metadata.** Events contain only the data needed by the current domain model. Actor IDs, timestamps, correlation IDs, and other audit metadata are omitted because audit processing is outside the scope of this exercise.
- **Lightweight architecture.** Separate ports, event classes, and read DTOs introduce some additional abstractions for a feature with only two commands and one read use case. They were kept to make domain and infrastructure boundaries explicit, not to build a full CQRS framework. Commands and queries still share the same application service.
- **No persisted Event Sourcing snapshots.** The aggregate is rebuilt by replaying its event history. For the expected size of the event stream, this is simpler than maintaining snapshot versions and consistency. `PayrollLineSnapshot` and `AdjustmentSnapshot` are read DTOs, not persisted aggregate snapshots.

The project also leaves out database integration, event serialization and schema evolution, command idempotency, authentication, authorization, currency handling, and separate read-side storage. These concerns can be introduced when actual requirements justify them.

## Running the Project

Install dependencies:

```bash
composer install
```

Run the test suite:

```bash
composer test
```

Run the CLI example:

```bash
composer example
```

## Tests and CI

The PHPUnit suite covers domain invariants, event replay, adjustment history, optimistic concurrency, and Event Store behavior.

GitHub Actions runs Composer validation, installs dependencies, executes the test suite, and runs the CLI example on PHP 8.5.

## Production Considerations

Before production use, the main areas to revisit would be durable event storage with atomic concurrency control, overflow-safe monetary calculations, audit metadata, command idempotency, and event schema versioning.

Persisted aggregate snapshots or a separate read model should be introduced only when event volume or performance requirements make their additional complexity worthwhile.