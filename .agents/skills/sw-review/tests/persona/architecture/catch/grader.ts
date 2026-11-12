// Plant: repository->search() called inside foreach over cart line items.
// Per personas/architecture.md: "Hot paths ... avoid N+1, sync I/O in loops".
// Expectation: at least one finding categorised performance OR maintainability.

import { atLeastMinor, category, findings, grade } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('has-finding', findings(out).length >= 1);
    g.check('perf-or-maint-category', findings(out).some(category('performance', 'maintainability', 'correctness')));
    g.check('non-nit-severity', findings(out).some(atLeastMinor));
});
