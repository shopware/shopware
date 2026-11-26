// Real diff: a change moved theme-folder cleanup from DeleteThemeFilesTaskHandler
// into theme:compile / theme:change because the scheduled task "does not run
// reliably in some environments". Outcome: cached storefront pages requested
// CSS/JS from deleted folders and got 404s.
// Guides loaded: platform-scope (SCOPE-001) and cache-and-asset-lifetime (CACHE-001..003).
// Expectation: a SCOPE- or CACHE- finding, major or blocking, category scope or
// correctness; the scope finding carries requires_human.

import { all, any, category, findings, grade, majorOrBlocking, requiresHuman, rule, ruleMatches } from './_lib.ts';

const GUIDE_RULE = ruleMatches(/^(SCOPE|CACHE)-/);

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'maintainer');
    g.require('guide-rule-finding', findings(out).some(GUIDE_RULE));
    g.require('major-or-blocking', findings(out).some(all(GUIDE_RULE, majorOrBlocking)));
    g.check('scope-or-correctness', findings(out).some(all(GUIDE_RULE, category('scope', 'correctness'))));
    g.require('scope-requires-human', findings(out).filter(any(category('scope'), rule('SCOPE-'))).every(requiresHuman));
});
