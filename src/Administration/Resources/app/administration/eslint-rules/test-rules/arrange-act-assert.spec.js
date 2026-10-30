/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./arrange-act-assert');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

ruleTester.run('arrange-act-assert', rule, {
    valid: [
        {
            name: 'all three steps in order',
            filename: 'src/x/tests/pinned.spec.ts',
            code: `
it('returns null', () => {
    // ARRANGE
    const order = {};

    // ACT
    const shown = getPrimaryDelivery(order);

    // ASSERT
    expect(shown).toBeNull();
});`,
        },
        {
            name: 'nothing to arrange',
            filename: 'src/x/tests/requirements.spec.ts',
            code: `
it.deprecated('v6.8.0.0')('loads sorted', async () => {
    // ACT
    const criteria = await loadOrderCriteria();

    // ASSERT
    expect(criteria.sortings).toEqual([]);
});`,
        },
        {
            name: 'other specs are not checked',
            filename: 'src/x/tests/sw-example.spec.js',
            code: "it('works', () => { expect(true).toBe(true); });",
        },
    ],
    invalid: [
        {
            name: 'no step comments',
            filename: 'src/x/tests/regressions.spec.ts',
            code: "it('works', () => { expect(true).toBe(true); });",
            errors: [{ messageId: 'missingStep' }],
        },
        {
            name: 'code before act without arrange',
            filename: 'src/x/tests/pinned.spec.ts',
            code: `
it('returns null', () => {
    const order = {};

    // ACT
    const shown = getPrimaryDelivery(order);

    // ASSERT
    expect(shown).toBeNull();
});`,
            errors: [{ messageId: 'missingArrange' }],
        },
        {
            name: 'assert before act',
            filename: 'src/x/tests/pinned.spec.ts',
            code: `
it('returns null', () => {
    // ASSERT
    expect(true).toBe(true);

    // ACT
    run();
});`,
            errors: [{ messageId: 'wrongOrder' }],
        },
    ],
});
