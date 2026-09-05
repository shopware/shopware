# Choosing an executor: http & direct

Pick the cheapest executor that faithfully exercises the symptom:

- **playwright** — anything rendered: visual, layout, interaction, missing/wrong UI text, browser
  state. A *visual* issue must use this. See [playwright.md](playwright.md).
- **http** — Store API / Admin API / Sync API JSON behaviour, no rendering involved.
- **direct** — internal PHP service/DAL behaviour that can't fire faithfully through the API or UI
  (license-gated, heavy domain setup). A PHPUnit integration test.

## http

Requests and assertions live in `reproduction-plan.json` (see [plan.md](plan.md)). The executor owns
auth by surface — `/api/*` gets an admin Bearer token, `/store-api/*` gets the sales-channel key — so
drop any auth headers of your own. Multi-step: use `requests: [...]`; `sw-context-token` is captured
and carried forward, and a non-final setup request that isn't 2xx makes the leg `blocked`.

A request may set arbitrary `headers` (placeholders allowed in values), including **empty** ones —
useful for header-handling bugs, e.g. `{ "method": "GET", "path": "/api/language",
"headers": { "sw-language-id": "" } }`.

**Entity ids must be placeholders, never literal install ids.** The exact plan is replayed on both
legs, and every install generates different UUIDs for countries, salutations, payment/shipping
methods, and the like. A literal id resolved on the reported shop won't exist on trunk — the request
`400`s and the leg comes back `blocked` instead of a verdict (this is what happened on #2). Use the
per-leg placeholders (`{{COUNTRY}}`, `{{SALUTATION}}`, `{{SALUTATION2}}`, `{{TAX}}`, `{{CURRENCY}}`,
`{{PAYMENT_METHOD}}`, `{{SHIPPING_METHOD}}`, `{{CUSTOMER_GROUP}}`, `{{LANGUAGE}}`,
`{{SYSTEM_LANGUAGE}}`, … — full list in [fixtures.md](fixtures.md)) in the path, body, **and** the
assertion `expect`; the executor resolves each against the running leg's DB. If you need an entity
that has no placeholder, seed it in `fixtures.json` with a stable id and reference that id.
`repro validate` rejects bare install ids in an http plan. (It's fine to *create* an entity in a
request body with your own literal `"id"` and reference that id later — you're setting it identically
on both legs; the check only flags literals that point at pre-existing install entities.)

Assertion fields are jq filters on the final response. Ops: `equals` (default), `contains`,
`matches`, `present`, `absent`, `gt`, `lt`. `expect` is the **healthy** value. Mark setup checks
`"role": "precondition"` and the symptom `"role": "assert"`.

**Assertions run in order and stop at the first failure** (like reading a test top to bottom). The
failing check's role decides the leg: a **precondition** → `inconclusive` (the scenario wasn't set
up); an **assert** → `reproduced` (the symptom). All pass ⇒ `not_reproduced`. So **order them**:
reachability/status checks first, then the symptom, then deeper body checks.

**Assert the response body, not just the status** — a status code alone is weak evidence (many things
return the same code). Add assertions on the response **value** that's wrong/missing when the bug is
present: `.data present`, `.total equals 0`, `.errors[0].code contains "…"`, a computed price.

Two shapes, depending on the healthy response:
- Healthy is **2xx** (e.g. a wrong-value bug): make `status == 200` a `precondition`, then assert the
  body field. A buggy non-2xx fails the precondition → `inconclusive`; a buggy 2xx with the wrong
  value fails the assert → `reproduced`.
- Healthy is 2xx but the bug makes it **error out** (e.g. #25's 412 crash): make `status == 200` the
  first `assert`. The buggy error fails it → `reproduced`, and because evaluation stops there, the
  later body checks (which would be unreadable on the error) simply don't run — no `inconclusive`.
  Add those body checks anyway for the healthy leg (`.data present`, a returned value…).

(Safety net: if a value comparison targets a field jq can't read — a bad filter, a non-JSON body, or
a wrong-shape access — the leg is `inconclusive` (never a bogus `reproduced`) on any status, and jq's
error is surfaced in the leg output so you can fix it during `repro try`; `present`/`absent` are
exempt. **If the shape itself is the symptom** — an array where an object is expected, a changed
field count — assert a *readable* projection like `.data | type` (`equals "object"`) or
`.data | length` rather than relying on jq erroring; that makes the difference a real value
comparison that also flips correctly on trunk.)

## direct — `ReproTest.php`

A PHPUnit integration test under `Shopware\Tests\Integration\` asserting the healthy behaviour:

```php
<?php declare(strict_types=1);
namespace Shopware\Tests\Integration\Repro;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
// use IntegrationTestBehaviour / KernelTestBehaviour as the service under test needs.

class ReproTest extends TestCase
{
    public function testHealthy(): void
    {
        // arrange via the container/DAL, act, then assert the FIXED behaviour.
        static::assertSame(19.99, $result->getUnitPrice());
    }
}
```

Status: `OK` ⇒ `not_reproduced`. A `FAILURES!` or `ERRORS!`/fatal counts as `reproduced` **only when
it matches `assertion.symptom_pattern`** — a regex you **must** set on a direct plan. Mark your
SYMPTOM assertion with a distinctive token in its message and match it, e.g.
`static::assertFalse($item->isStackable(), 'REPRO_SYMPTOM: line item became stackable');` with
`"symptom_pattern": "REPRO_SYMPTOM"`. A failure that does **not** match is `inconclusive` (treated as a
failed setup/precondition assertion, not the reported symptom) — so setup asserts stay unmarked. For
an exception symptom (e.g. a DAL write that throws during synchronous indexing), `symptom_pattern`
matches the exception text instead.
