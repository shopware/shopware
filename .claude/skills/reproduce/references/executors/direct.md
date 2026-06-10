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
