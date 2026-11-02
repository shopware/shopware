// Plant: base trunk, two new 6.7 migrations. Migration1791537318 drops
// `product_sorting.locked` in update(), inside an `if` block, so PHPStan
// NoDropStatementInUpdateRule (top-level statements only) does not see it; the
// old release still reads the column during a blue-green deployment.
// Migration1791537247 adds an index with a raw ALTER TABLE and no indexExists()
// guard, so a re-run after a timeout fails; its test runs update() only once.
// Guide migrations (MIG-002, MIG-001, MIG-010).
// Expectation: a MIG- finding with severity major or blocking.

import { all, file, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('mig-rule-finding', findings(out).some(rule('MIG-')));
    g.require('mig-major-or-blocking', findings(out).some(all(rule('MIG-'), majorOrBlocking)));
    g.check('drop-in-update-found', findings(out).some(file('src/Core/Migration/V6_7/Migration1791537318RemoveLockedFromProductSorting.php')));
    g.check(
        'idempotency-found',
        findings(out).some(
            file(
                'src/Core/Migration/V6_7/Migration1791537247AddIsSystemToProductSorting.php',
                'tests/migration/Core/V6_7/Migration1791537247AddIsSystemToProductSortingTest.php',
            ),
        ),
    );
});
