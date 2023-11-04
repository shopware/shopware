/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsSmippetFilterSwitch from 'src/module/sw-settings-snippet/component/sidebar/sw-settings-snippet-filter-switch';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

Shopware.Component.register('sw-settings-snippet-filter-switch', swSettingsSmippetFilterSwitch);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-snippet-filter-switch'), {
        localVue,
        stubs: {
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
        },
        propsData: {
            name: 'Shopware',
        },
    });
}

describe('sw-settings-snippet-filter-switch', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
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
