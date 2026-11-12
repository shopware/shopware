// Real diff: a change streamed private downloads through PHP for every shop
// because one hosting setup had a storage endpoint the browser could not reach.
// Closed in favour of documenting the requirement.
// Expectation: a SCOPE- finding (SCOPE-002, options / changed default for one
// setup), severity major, category scope, requires_human.

import { all, any, category, findings, grade, requiresHuman, rule, severity } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'maintainer');
    g.require('scope-rule-finding', findings(out).some(rule('SCOPE-')));
    g.check('scope-major', findings(out).some(all(rule('SCOPE-'), severity('major'))));
    g.check('scope-category', findings(out).some(all(rule('SCOPE-'), category('scope'))));
    g.check('scope-requires-human', findings(out).filter(any(category('scope'), rule('SCOPE-'))).every(requiresHuman));
});
