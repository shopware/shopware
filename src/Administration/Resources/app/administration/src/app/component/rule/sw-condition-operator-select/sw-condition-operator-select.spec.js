/**
 * @sw-package fundamentals@after-sales
 */

import { shallowMount } from '@vue/test-utils';

async function createWrapper(customProps = {}) {
    return shallowMount(await wrapTestComponent('sw-condition-operator-select', { sync: true }), {
        props: {
            condition: {},
            operators: [],
            ...customProps,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-single-select': true,
            },
        },
    });
}

describe('src/app/component/rule/sw-condition-operator-select', () => {
    it('should have enabled fields', async () => {
        const wrapper = await createWrapper();

        const singleSelect = wrapper.get('sw-single-select-stub');

        expect(singleSelect.attributes().disabled).toBeUndefined();
    });

    it('should have disabled fields', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const singleSelect = wrapper.get('sw-single-select-stub');

        expect(singleSelect.attributes().disabled).toBe('true');
    });

    it('emits only operator when changed to empty operator', async () => {
        const wrapper = await createWrapper({
            condition: {
                value: {
                    operator: '>=',
                    value: 'Test',
                },
            },
            operators: [{ value: '>=', label: 'Greater than or equal' }],
        });

        await wrapper.vm.changeOperator('empty');

        expect(wrapper.emitted('change')).toEqual([
            [{ value: { operator: 'empty' } }],
        ]);
    });
    it('preserves all condition properties when changed to a non-empty operator', async () => {
        const wrapper = await createWrapper({
            condition: {
                value: {
                    operator: '==',
                    amount: 5,
                    value: 'Test',
                },
            },
            operators: [
                { value: '==', label: 'Equals' },
                { value: '>=', label: 'Greater than or equal' },
            ],
        });

        await wrapper.vm.changeOperator('>=');

        expect(wrapper.emitted('change')).toEqual([
            [
                {
                    value: {
                        operator: '>=',
                        amount: 5,
                        value: 'Test',
                    },
                },
            ],
        ]);
    });
});
