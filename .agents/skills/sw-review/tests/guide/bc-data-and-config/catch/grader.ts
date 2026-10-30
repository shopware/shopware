// Plant: base trunk, V6_7 migration. update() makes the existing nullable column
// customer_address.phone_number NOT NULL (blue-green unsafe: the previous release
// still inserts addresses without a phone number), and the DAL field gets
// new Required(), so Admin/Store API writes without the field now fail. No flag,
// no release docs, no plan for the next major.
// Guide bc-data-and-config (BCDATA-001).
// Expectation: a BCDATA- finding with severity major or blocking.

import { all, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.check('rule-finding', findings(out).some(rule('BCDATA-')));

    // The planted expectation is decisive: when it fails, the score is 0 no matter
    // how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
    g.require('rule-severity', findings(out).some(all(rule('BCDATA-'), majorOrBlocking)));
});
