/**
 * @sw-package framework
 *
 * The factory itself: one rule per entry, fix levels from the usage type or the usage, and the
 * report message with the context of the entry.
 */

const { createApiUsageRule } = require('../api-usage-rule');
const { scriptTester } = require('./tester');

function entry(id, usage) {
    return {
        id,
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description: `Description of ${id}.`,
        usage,
    };
}

const STATE_ENTRY = entry('api.state', [
    { type: 'member-usage', object: 'Shopware', properties: ['State'], message: 'Shopware.State is removed.' },
]);

const MANUAL_REPLACEMENT_ENTRY = entry('api.manual-replacement', [
    {
        type: 'replace-component-member',
        fix: 'manual',
        components: ['sw-order-list'],
        name: 'dateFilter',
        with: "Shopware.Filter.getByName('date')",
        message: 'dateFilter is removed.',
        templateMessage: 'Add a dateFilter computed.',
    },
]);

describe('createApiUsageRule()', () => {
    it('describes the rule with the description of its entry', () => {
        expect(createApiUsageRule(STATE_ENTRY).meta.docs.description).toBe('Description of api.state.');
    });

    it('throws for a usage type it does not know', () => {
        expect(() => createApiUsageRule(entry('api.typo', [{ type: 'member-usages', properties: ['a'] }]))).toThrow(
            'Unknown usage type "member-usages" in "api.typo".',
        );
    });
});

scriptTester.run('createApiUsageRule() of one entry', createApiUsageRule(STATE_ENTRY), {
    valid: [
        {
            name: 'a usage of another entry',
            code: "this.$store.commit('x');",
        },
    ],
    invalid: [
        {
            name: 'the context of an entry without UPGRADE sections',
            code: "Shopware.State.get('x');",
            errors: [{ message: 'Shopware.State is removed.\nDescription of api.state.\nRemoved in Shopware 6.8.0.' }],
        },
    ],
});

scriptTester.run('createApiUsageRule() with a fix level of the usage', createApiUsageRule(MANUAL_REPLACEMENT_ENTRY), {
    valid: [],
    invalid: [
        {
            name: 'a replacement the usage declares manual is reported without a fix',
            code: "Shopware.Component.override('sw-order-list', { methods: { a(v) { return this.dateFilter(v); } } });",
            output: null,
            errors: [{ message: /^dateFilter is removed\.\n/ }],
        },
    ],
});
