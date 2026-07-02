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

(Safety net: if the deciding assert is a value comparison on a field that's unreadable on a non-2xx
response, the leg is `inconclusive` rather than a bogus `reproduced`; `present`/`absent` are exempt.)

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

Status: `OK` ⇒ `not_reproduced`; `FAILURES!` ⇒ `reproduced`; `ERRORS!`/fatal ⇒ `inconclusive`
(cross-version mismatch) — **unless** the symptom is an exception: set `assertion.symptom_pattern`
to a regex, and a matching error counts as `reproduced` (DAL writes throw during synchronous
indexing, so the symptom often escapes a try/catch around a later call).
