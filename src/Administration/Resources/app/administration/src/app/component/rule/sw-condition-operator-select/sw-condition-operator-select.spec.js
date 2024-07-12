/**
 * @group disabledCompat
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
});
