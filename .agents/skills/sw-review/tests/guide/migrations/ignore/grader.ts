// Clean: base trunk, one additive 6.7 migration. The column is added with
// addColumn(), the index behind TableHelper::indexExists() through
// executeDdlStatement(), the data update only touches unmarked rows, the
// timestamp is exact, updateDestructive() is empty and untested, and the
// migration test runs update() twice. Guide migrations, "Do not flag".
// Expectation: no MIG- finding, nothing major or blocking.

import { findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('no-mig-finding', !findings(out).some(rule('MIG-')));
    g.check('no-major-or-blocking', !findings(out).some(majorOrBlocking));
});
