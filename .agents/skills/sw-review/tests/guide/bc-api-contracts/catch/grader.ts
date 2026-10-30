// Plant: /store-api/handle-payment renames response field redirectUrl to
// paymentRedirectUrl and reads a new request header sw-payment-finish-url,
// no OpenAPI schema change. Guide bc-api-contracts (BCAPI-001, BCAPI-003,
// BCAPI-004). Expectation: a BCAPI- finding with severity major or blocking.

import { all, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.check('rule-finding', findings(out).some(rule('BCAPI-')));

    // The planted expectation is decisive: when it fails, the score is 0 no matter
    // how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
    g.require('rule-severity', findings(out).some(all(rule('BCAPI-'), majorOrBlocking)));
});
