// Plant: base trunk. A bug fix in ProductCartProcessor (purchase steps below 1
// are treated as 1, fixQuantity() never goes below the minimum purchase) with
// no test change at all. The PR fixes an issue (metadata fixes_issue: true).
// Danger MissingUnitTests only covers new src files, so nothing in CI asks for
// the test. Guide tests-for-behavior-change (TEST-001).
// Expectation: a TEST- finding with severity major or blocking on the processor.

import { all, category, file, findings, grade, majorOrBlocking, rule } from './_lib.ts';

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('test-rule-finding', findings(out).some(rule('TEST-')));
    g.require('test-major-or-higher', findings(out).some(all(rule('TEST-'), majorOrBlocking)));
    g.check('on-changed-file', findings(out).some(all(rule('TEST-'), file('src/Core/Content/Product/Cart/ProductCartProcessor.php'))));
    g.check('category-tests', findings(out).some(all(rule('TEST-'), category('tests'))));
});
