// Plant: base trunk. LineItemQuantitySplitter (non-internal) renames its public
// method split() to splitQuantity() and updates the core callers, with no
// forwarding old method, no @deprecated tag, no BC-change attribute and no
// RELEASE_INFO / UPGRADE entry. roave would report the removed method; the
// planted problem is the missing deprecation path and release docs.
// Guide bc-php (BCPHP-001, BCPHP-006).
// Expectation: a BCPHP- finding with severity major or blocking.

import { all, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.check('rule-finding', findings(out).some(rule('BCPHP-')));

    // The planted expectation is decisive: when it fails, the score is 0 no matter
    // how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
    g.require('rule-severity', findings(out).some(all(rule('BCPHP-'), majorOrBlocking)));
});
