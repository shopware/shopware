---
title: Validate DAL writes against their outcome
date: 2026-10-23
area: framework
tags: [dal, validation, sync-api]
---

## Context

Some DAL validations inspect a record as it will be after it is written to the database.

About eight checks in core do this, and each one works out the record on its own, from the write commands and its own database query.
They use one of two patterns:

* **Last command per record.** The check keeps one command per record and loads the stored rows in one query. A later write in the same Sync request hides an earlier one, so invalid data can be stored.
* **Each command on its own.** The check runs once per command and fills in the stored values for the fields the command doesn't set. This costs one query per command, and it rejects requests that are only valid once all their writes are applied.

Most checks in core use the second pattern.

To be correct, a check has to merge all writes to a record in order, on top of the stored values.
Every check would have to repeat that.

Other checks need a record as it is stored before the write, for example to block deleting a record that is still in use, or to check who owns a record before it is changed.
They load the stored rows themselves, often with one query per command.

## Decision

The DAL works out each record as it will be after the write, and as it was stored before it.

A new **WriteOutcome** service does this.
You pass it the commands for one entity and the columns your check needs.
It loads the stored values for all records in one query.

* `resultingRows()` returns one row per record, with all writes to that record merged in order on top of the stored values.
  It only returns records that are new, or whose writes set one of the columns.
  Records the request deletes are not returned.
* `touchedRows()` returns one row for every record the request creates, updates or deletes, whatever columns its writes set.
  Each row holds the stored values and the values after the write, and says whether the record is new or deleted.
* Values come back the way they are stored, for example ids as binary.

## Consequences

* Checks no longer merge commands or query the rows they validate themselves.

## Example

The rule: a promotion's discount may be at most 50%, unless the promotion is marked as clearance.

Request A writes the same promotion twice in one Sync request (`POST /api/_action/sync`).
The promotion ends up with a 90% discount and no clearance flag, so the request must be rejected:

```json
[
    {
        "entity": "promotion",
        "action": "upsert",
        "payload": [{ "id": "0190d6c4a1b27c3f8e5d9a4b6c2f1e07", "discount": 90 }]
    },
    {
        "entity": "promotion",
        "action": "upsert",
        "payload": [{ "id": "0190d6c4a1b27c3f8e5d9a4b6c2f1e07", "name": "Summer sale" }]
    }
]
```

Request B is the same, except the second write sets `"clearance": true` instead of the name.
The promotion would end up with a 90% discount and the clearance flag, so it should be accepted.

### Last command per record

```php
$promotions = [];
foreach ($event->getCommandsForEntity('promotion') as $command) {
    // a later write replaces an earlier one
    $promotions[$command->getPrimaryKey()['id']] = $command;
}
```

Request A: only the rename is checked, because the discount is set in the first command. The request is accepted and the 90% discount is stored. **Wrong.**

### Each command on its own

```php
foreach ($event->getCommandsForEntity('promotion') as $command) {
    $discount = $command->getPayload()['discount'] ?? $this->loadStored($command)['discount'];
}
```

Request A: the first write has a 90% discount and no clearance flag. The request is rejected. **Right.**

Request B: the first write is checked before the clearance flag is set in the second command. The request is rejected. **Wrong.**

### With WriteOutcome

```php
$commands = $event->getCommandsForEntity('promotion');

foreach ($this->writeOutcome->resultingRows($commands, ['discount', 'clearance']) as $promotion) {
    if ($promotion->getResultingValue('discount') > 50 && !$promotion->getResultingValue('clearance')) {
        $this->reject($promotion->path);
    }
}
```

Request A: `resultingRows()` returns the promotion with a 90% discount and no clearance flag. The request is rejected. **Right.**

Request B: `resultingRows()` returns the promotion with a 90% discount and the clearance flag. The request is accepted. **Right.**
