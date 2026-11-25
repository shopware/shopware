// Plant: RenderedElementFactory seeds a declared default at serve time (stored-model.md: defaults are
// seeded at write time, never at serve time) and swallows the unsupported-value exception, rendering an
// element without properties instead of failing (failure-and-loss.md: nothing degrades silently).
// Guide content-system-principles (CSP-003, CSP-006). Expectation: a CSP- finding with severity major or
// blocking on the factory file.

import { all, file, findings, grade, majorOrBlocking, rule } from './_lib.ts';

const FACTORY = 'src/Core/Framework/ContentSystem/Rendering/RenderedElementFactory.php';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.check('rule-finding', findings(out).some(rule('CSP-')));

    // The planted expectation is decisive: when it fails, the score is 0 no matter
    // how many schema checks pass.
    g.require('rule-severity', findings(out).some(all(rule('CSP-'), majorOrBlocking, file(FACTORY))));
});
