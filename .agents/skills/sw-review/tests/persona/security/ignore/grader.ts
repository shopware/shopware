// Out-of-lens: storefront snippet copy tweak. Security persona has no claim on
// i18n strings. Expectation: zero findings.

import { findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'security');
    g.require('no-findings', findings(out).length === 0);
    g.check('decision-comment', out.decision === 'comment');
});
