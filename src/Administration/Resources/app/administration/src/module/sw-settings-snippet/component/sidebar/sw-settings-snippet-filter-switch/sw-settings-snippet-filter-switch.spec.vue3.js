/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils_v3';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-snippet-filter-switch', {
        sync: true,
    }), {
        global: {
            stubs: {
                'sw-field': await wrapTestComponent('sw-field'),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
            },
        },
        props: {
            name: 'Shopware',
        },
    });
}

describe('sw-settings-snippet-filter-switch', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain a prop property, called: value', async () => {
        expect(wrapper.vm.value).toBe(false);
        await wrapper.setProps({
            value: true,
        });
        expect(wrapper.vm.value).toBe(true);

        const fieldSwitchInput = wrapper.find('.sw-field--switch__input input');
        expect(fieldSwitchInput.attributes('name')).toBe('Shopware');
    });
});
