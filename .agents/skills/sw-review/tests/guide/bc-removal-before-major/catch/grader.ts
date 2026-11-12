// Synthetic diff: deletes the deprecated public Twig function `sw_cover_url`
// (ProductCoverUrlExtension under src/Storefront) and adds an UPGRADE-6.8 entry.
// The deprecation cycle and the release docs are in place, but nothing states the
// measured ecosystem impact or whether a forwarding shim was considered.
// Per guides/bc-removal-before-major.md (REMOVAL-001..003): expect a REMOVAL-
// finding with requires_human, since keeping or removing is a human decision.

import { all, blocking, findings, grade, requiresHuman, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'maintainer');
    g.require('removal-rule-finding', findings(out).some(rule('REMOVAL-')));
    g.require('removal-requires-human', findings(out).some(all(rule('REMOVAL-'), requiresHuman)));
    g.check('removal-not-blocking', !findings(out).some(all(rule('REMOVAL-'), blocking)));
});
