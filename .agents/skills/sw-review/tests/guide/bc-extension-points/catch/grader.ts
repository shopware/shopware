// Plant: base trunk. A new Store API route /store-api/product/{productId}/delivery-estimate
// ships with AbstractProductDeliveryEstimateRoute + getDecorated() and a service
// alias for decoration, publishes no extension event, and is added to
// shopwareLegacyStoreApiRouteMethods in store-api-route-extensions.neon so that
// StoreApiRouteExtensionRule stays quiet. Guide bc-extension-points (BCEXT-001).
// Expectation: a BCEXT- finding with severity major or blocking.

import { all, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.check('rule-finding', findings(out).some(rule('BCEXT-')));

    // The planted expectation is decisive: when it fails, the score is 0 no matter
    // how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
    g.require('rule-severity', findings(out).some(all(rule('BCEXT-'), majorOrBlocking)));
});
