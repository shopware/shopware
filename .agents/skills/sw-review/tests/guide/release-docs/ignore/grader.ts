// Clean: base trunk. A plain bug fix in ProductCartProcessor (purchase steps
// below 1 no longer divide by zero) with a unit test, and no RELEASE_INFO or
// UPGRADE entry. Bug fixes without a contract change get no entry; Danger
// MissingReleaseInfo already prints its generic reminder.
// Guide release-docs (DOCS-001, "Do not flag").
// Expectation: no DOCS- finding and no docs-category finding.

import { blocking, category, findings, grade, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'maintainer');
    g.require('no-docs-rule-finding', !findings(out).some(rule('DOCS-')));
    g.check('no-docs-category', !findings(out).some(category('docs')));
    g.check('no-blocking', !findings(out).some(blocking));
});
