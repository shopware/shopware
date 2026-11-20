// Plant: admin twig adds button + hint with hardcoded English strings, no
// snippet key. Per personas/ux.md: "Admin snippets: no hard-coded user-facing
// strings". Expectation: major finding under maintainability/docs.

import { atLeastMinor, findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'ux');
    g.require('has-finding', findings(out).length >= 1);
    g.check('non-nit-severity', findings(out).some(atLeastMinor));
});
