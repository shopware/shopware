// Out-of-lens: new core migration adding a column. UX has no claim.
// Expectation: zero findings.

import { findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'ux');
    g.require('no-findings', findings(out).length === 0);
    g.check('decision-comment', out.decision === 'comment');
});
