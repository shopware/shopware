// Plant: base trunk, a new config option `shopware.sitemap.max_urls_per_file`.
// The RELEASE_INFO entry is added under `# 6.7.14.2`, a released section,
// instead of `# 6.7.16.0 (upcoming)`; the PR has no milestone label, so the
// release-info/section check reports nothing. The UPGRADE-6.8 entry is written
// in future tense ("We will lower ...") and spends most of its text on the
// reasoning. Guide release-docs (DOCS-003, DOCS-004, DOCS-005).
// Expectation: a DOCS- finding on RELEASE_INFO-6.7.md and a DOCS-003 finding
// (entry added to a released section); a DOCS- finding on
// UPGRADE-6.8.md is expected but not decisive.

import { all, blocking, file, findings, grade, rule, ruleIn } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'maintainer');
    g.require('docs-rule-finding', findings(out).some(rule('DOCS-')));
    g.require('released-section-finding', findings(out).some(all(rule('DOCS-'), file('RELEASE_INFO-6.7.md'))));
    g.require('docs-003-finding', findings(out).some(ruleIn(['DOCS-003'])));
    g.check('upgrade-wording-finding', findings(out).some(all(rule('DOCS-'), file('UPGRADE-6.8.md'))));
    g.check('docs-not-blocking', !findings(out).some(all(rule('DOCS-'), blocking)));
});
