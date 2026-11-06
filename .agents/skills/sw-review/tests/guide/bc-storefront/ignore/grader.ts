// Clean: inner markup of component_pagination_item_link_text changes while the
// block keeps its name and position; the private _resumeFocusState() helper of
// ListingPaginationPlugin is inlined and removed. Guide bc-storefront lists both
// under "Do not flag". Expectation: no BCSF- finding.

import { blocking, findings, grade, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'ux');
    g.check('no-rule-finding', !findings(out).some(rule('BCSF-')));
    g.check('no-blocking', !findings(out).some(blocking));
});
