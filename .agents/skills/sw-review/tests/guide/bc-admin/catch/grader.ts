// Plant: required prop entityName added to the public sw-media-field component
// and its "label" slot removed, no deprecation. Guide bc-admin (BCADM-001,
// BCADM-002). Expectation: a BCADM- finding with severity major or blocking.

import { all, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.check('rule-finding', findings(out).some(rule('BCADM-')));

    // The planted expectation is decisive: when it fails, the score is 0 no matter
    // how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
    g.require('rule-severity', findings(out).some(all(rule('BCADM-'), majorOrBlocking)));
});
