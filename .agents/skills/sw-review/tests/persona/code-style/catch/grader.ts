// Plant: method `findBenutzerByEmail`, parameter `$benutzer`, German comment in
// an otherwise English file. Per personas/code-style.md: "Mixed German/English
// identifiers or public docs." Expectation: at least one minor finding.

import { blocking, category, findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'code-style');
    g.require('has-finding', findings(out).length >= 1);
    g.check('maint-category', findings(out).some(category('maintainability')));
    g.check('no-blocking', !findings(out).some(blocking));
});
