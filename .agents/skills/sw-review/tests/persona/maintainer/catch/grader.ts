// Plant: new file under changelog/_unreleased/. Per personas/maintainer.md:
// "changelog/_unreleased/ is legacy; new files there are wrong."
// Expectation: at least one finding under docs.

import { category, findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'maintainer');
    g.require('has-finding', findings(out).length >= 1);
    g.check('docs-category', findings(out).some(category('docs')));
});
