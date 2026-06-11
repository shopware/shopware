# Executor: `direct` (service / DAL — PHPUnit integration test)

Use when the bug can't fire faithfully through store-api (`http`) or the UI (`playwright`)
— license-gated paths, an internal service/indexer/calculation, or heavy domain setup. The
symptom is typically a computed value or a service behaviour, not an HTTP/DOM surface.

## What you author
Generate `ReproTest.php` (set `script_path: "ReproTest.php"`):
- namespace `Shopware\Tests\Integration\Repro`
- `class ReproTest extends TestCase` using `IntegrationTestBehaviour` (which pulls in
  `KernelTestBehaviour`); resolve services via `$this->getContainer()`.
- **REUSE the fix PR's regression-test setup** where it exists (`./fixpr.diff`) — do NOT
  reinvent the bootstrap.
- A single test method that ASSERTS THE HEALTHY (fixed) behaviour, so it FAILS on the buggy
  version and PASSES when healthy.
- **When the SYMPTOM is a thrown exception** (the buggy version throws — e.g. a DB error
  during indexing), wrap exactly that action in try/catch and convert the throw into an
  assertion FAILURE:
  ```php
  try {
      $indexer->handle($message); // the action that throws on the buggy version
  } catch (\Throwable $e) {
      static::fail('symptom: ... threw ' . $e->getMessage());
  }
  ```
  Without this, the throw surfaces as a PHPUnit ERROR, which the executor classifies as
  `inconclusive` (reserved for bootstrap/compile failures) — not as the reproduction it is.
  Keep setup OUTSIDE the try/catch so genuine setup breakage still errors → `inconclusive`.
- **DAL writes run indexers SYNCHRONOUSLY in integration tests** (`EntityIndexingSubscriber`
  dispatches on entity-written). If the symptom is thrown FROM an indexer, the triggering
  **write IS the action** — wrap THAT `$repository->create()/upsert()` in the try/catch
  (mirroring the issue's "create a product"), NOT a later explicit indexer call that is
  never reached. (A real miss: the product create() at "setup" line 80 ran ProductIndexer
  inline and threw there → ERROR → inconclusive, while the wrapped indexer call below it
  was dead code.) If heavy FIXTURES would also trigger the same indexer prematurely,
  create them with indexing deferred — `$context->addState(EntityIndexerRegistry::DISABLE_INDEXING)`
  (or `EntityIndexerRegistry::USE_INDEXING_QUEUE`) during fixture writes — then perform the
  one real triggering write inside the try/catch with a normal context.

`run-direct.sh` drops the file under the shop's `tests/integration/Repro/` (PSR-4
autoload) and runs `vendor/bin/phpunit`.

## Cross-version faithfulness
Prefer STABLE service/DAL APIs so the SAME test also compiles + runs on the reported
version. If it can only compile on trunk, that is fine — the reported leg reports
`inconclusive` (not a bogus pass).

## How `run-direct.sh` classifies the PHPUnit summary
- `OK` → `not_reproduced` (healthy)
- `FAILURES!` → `reproduced` (the symptom assertion failed)
- `ERRORS!` / fatal / no-tests → `inconclusive` (couldn't bootstrap/compile — usually a
  cross-version API mismatch on the reported leg, never a bogus pass)
- anything else → `blocked`

## Comment every step
Comment the setup and the single healthy assertion (what it does + what it proves).
