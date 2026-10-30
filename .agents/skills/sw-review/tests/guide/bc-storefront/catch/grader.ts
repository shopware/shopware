// Plant: Storefront Twig block component_pagination_item_link_text renamed and
// the public paginationNavSelector option removed from ListingPaginationPlugin,
// both without deprecation. Guide bc-storefront (BCSF-001, BCSF-005).
// Expectation: a BCSF- finding with severity major or blocking.

import { all, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'ux');
    g.check('rule-finding', findings(out).some(rule('BCSF-')));

    // The planted expectation is decisive: when it fails, the score is 0 no matter
    // how many schema checks pass (otherwise 7/8 would clear the 0.8 threshold).
    g.require('rule-severity', findings(out).some(all(rule('BCSF-'), majorOrBlocking)));
});
