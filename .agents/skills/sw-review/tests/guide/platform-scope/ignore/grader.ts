// Synthetic diff: SendMailHandler turns a temporary 4xx SMTP answer into a
// RecoverableMessageHandlingException with a longer retry delay. Retries and
// backoff inside a message handler are correct async behaviour and are listed
// under "Do not flag" in guides/platform-scope.md.
// Expectation: no scope finding and no SCOPE- rule id. Other lenses (for example
// a minor test remark) may still comment; they are not this guide's concern.

import { blocking, category, findings, grade, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'maintainer');
    g.require('no-scope-category', !findings(out).some(category('scope')));
    g.require('no-scope-rule-id', !findings(out).some(rule('SCOPE-')));
    g.check('no-blocking', !findings(out).some(blocking));
});
