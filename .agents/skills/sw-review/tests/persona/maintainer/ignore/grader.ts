// Out-of-lens: SCSS brand colour tweak. maintainer has no claim on visual.
// Expectation: zero findings.

import { findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'maintainer');
    g.require('no-findings', findings(out).length === 0);
    g.check('decision-comment', out.decision === 'comment');
});
