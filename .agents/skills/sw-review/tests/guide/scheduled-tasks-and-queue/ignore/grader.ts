// Synthetic diff: a new daily scheduled task (CleanupUserRecoveryTask) with a final
// handler extending ScheduledTaskHandler, batched DELETE, DI registration with the
// shopware.scheduled.task tag and a unit test asserting the effect. This is the
// correct async pattern from guides/scheduled-tasks-and-queue.md.
// Expectation: no QUEUE- finding, no scope finding, nothing major or blocking.

import { category, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('no-queue-rule-id', !findings(out).some(rule('QUEUE-')));
    g.check('no-scope-category', !findings(out).some(category('scope')));
    g.require('no-major-or-blocking', !findings(out).some(majorOrBlocking));
});
