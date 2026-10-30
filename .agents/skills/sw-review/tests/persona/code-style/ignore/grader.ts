// Out-of-lens: composer.json dependency bump. code-style does not own deps.
// Expectation: zero findings.

import { findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'code-style');
    g.require('no-findings', findings(out).length === 0);
    g.check('decision-comment', out.decision === 'comment');
});
