// Plant: base trunk, Administration customer detail page. A new computed
// property checks `ACCESSIBILITY_TWEAKS` inside a `V6_8_0_0` branch: a nested
// flag check (ADMF-003). The template drops the
// `sw-customer-detail__before-content` extension section and the PR removes
// the entry from src/meta/position-identifiers.json, so the meta Jest test
// passes while app content on that screen disappears (ADMF-008).
// Guide admin-bc-and-flags.
// Expectation: an ADMF- finding; findings on the identifier removal and on the
// flag checks are expected but not decisive on their own.

import { all, file, findings, grade, majorOrBlocking, rule } from './_lib.ts';

const PI = 'src/Administration/Resources/app/administration/src/meta/position-identifiers.json';
const TWIG = 'src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-detail/sw-customer-detail.html.twig';
const JS = 'src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-detail/index.js';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'ux');
    g.require('admf-rule-finding', findings(out).some(rule('ADMF-')));
    g.check('position-identifier-finding', findings(out).some(all(rule('ADMF-'), file(PI, TWIG))));
    g.check('flag-finding', findings(out).some(all(rule('ADMF-'), file(JS))));
    g.check('major-or-higher', findings(out).some(all(rule('ADMF-'), majorOrBlocking)));
});
