// Clean: base trunk. LineItemQuantitySplitter::split() gets a new required
// parameter the BC-safe way: #[NewRequiredParameter] attribute, func_get_arg()
// shim with Feature::triggerDeprecationOrThrow('v6.8.0.0') for legacy calls,
// the new rounding only inside the existing Feature::isActive('v6.8.0.0')
// branch, core callers updated, RELEASE_INFO and UPGRADE entries present. The
// constructor change is allowed (service constructors are @internal).
// Guide bc-php, "Do not flag".
// Expectation: no BCPHP-001, -002, -003 or -006 finding. A BCPHP-007 finding
// (check ecosystem usage) is legitimate on this diff and does not count.

import { blocking, findings, grade, ruleIn } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('no-planted-bcphp', !findings(out).some(ruleIn(['BCPHP-001', 'BCPHP-002', 'BCPHP-003', 'BCPHP-006'])));
    g.check('no-blocking', !findings(out).some(blocking));
});
