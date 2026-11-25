// Out-of-lens: admin product snippet copy tweak. Architecture has no claim.
// Expectation: zero findings.

import { findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('no-findings', findings(out).length === 0);
    g.check('decision-comment', out.decision === 'comment');
});
