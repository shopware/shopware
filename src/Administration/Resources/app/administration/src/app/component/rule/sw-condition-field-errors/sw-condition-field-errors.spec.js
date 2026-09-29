/**
 * @sw-package fundamentals@after-sales
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-field-errors';

const error = { detail: 'invalid', code: 'INVALID' };

async function createWrapper(errors = {}, providedConditionType = null, mocks = {}) {
    return mount(await wrapTestComponent('sw-condition-field-errors', { sync: true }), {
        props: { errors },
        global: {
            stubs: {
                'mt-icon': true,
            },
            provide: {
                conditionType: providedConditionType,
            },
            mocks: {
                $te: () => true,
                $t: (key) => key,
                ...mocks,
            },
        },
    });
}

describe('src/app/component/rule/sw-condition-field-errors', () => {
    it('renders nothing when there are no errors', async () => {
        const wrapper = await createWrapper({});

        expect(wrapper.find('.sw-condition-field-errors').exists()).toBe(false);
    });

    it('renders nothing when every error entry is falsy', async () => {
        const wrapper = await createWrapper({
            fromTime: null,
            toTime: undefined,
        });

        expect(wrapper.find('.sw-condition-field-errors').exists()).toBe(false);
    });

    it('renders the prefix and resolved label for a single error', async () => {
        const wrapper = await createWrapper({
            fromTime: error,
        });

        expect(wrapper.find('.sw-condition-field-errors__prefix').text()).toBe('global.sw-condition.errors.prefix');
        expect(wrapper.find('.sw-condition-field-errors__summary').text()).toContain(
            'global.sw-condition-generic.null.fromTime.label',
        );
    });

    it('uses the provided conditionType when resolving the snippet path', async () => {
        const wrapper = await createWrapper({ amount: error }, 'cartLineItemWithQuantity');

        expect(wrapper.find('.sw-condition-field-errors__summary').text()).toContain(
            'global.sw-condition-generic.cartLineItemWithQuantity.amount.label',
        );
    });

    it('lists every label when up to five fields have errors', async () => {
        const wrapper = await createWrapper({
            fromTime: error,
            toTime: error,
            timezone: error,
            zipCodes: error,
            operator: error,
        });

        const summary = wrapper.find('.sw-condition-field-errors__summary').text();
        [
            'fromTime',
            'toTime',
            'timezone',
            'zipCodes',
            'operator',
        ].forEach((field) => {
            expect(summary).toContain(`global.sw-condition-generic.null.${field}.label`);
        });

        expect(summary).not.toContain('andMore');
    });

    it('truncates to the first five labels and appends an overflow counter', async () => {
        const wrapper = await createWrapper({
            fromTime: error,
            toTime: error,
            timezone: error,
            zipCodes: error,
            operator: error,
            amount: error,
            quantity: error,
        });

        const summary = wrapper.find('.sw-condition-field-errors__summary').text();

        expect(summary).toContain('global.sw-condition-generic.null.fromTime.label');
        expect(summary).toContain('global.sw-condition-generic.null.operator.label');
        expect(summary).toContain('global.sw-condition.errors.andMore');

        expect(summary).not.toContain('amount.label');
        expect(summary).not.toContain('quantity.label');
    });

    it('falls back to a humanized field name when no snippet matches', async () => {
        const wrapper = await createWrapper(
            {
                customRenderedField: error,
                another_value: error,
            },
            null,
            { $te: () => false },
        );

        const summary = wrapper.find('.sw-condition-field-errors__summary').text();

        expect(summary).toContain('Custom Rendered Field');
        expect(summary).toContain('Another Value');
    });
});
