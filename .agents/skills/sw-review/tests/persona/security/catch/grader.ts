// Plant: SQL string concatenation in DBAL query (no bound parameters).
// Per personas/security.md: "SQL/DBAL: no interpolated variables; use bound parameters."
// Expectation: blocking finding under category=security.

import { blocking, category, findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'security');
    g.require('has-finding', findings(out).length >= 1);
    g.check('security-category', findings(out).some(category('security')));
    g.check('blocking-severity', findings(out).some(blocking));
});
