import { shallowMount } from '@vue/test-utils';
import swExtensionMyExtensionsListingControls from 'src/module/sw-extension/component/sw-extension-my-extensions-listing-controls';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

Shopware.Component.register('sw-extension-my-extensions-listing-controls', swExtensionMyExtensionsListingControls);

/**
 * @package merchant-services
 */
// eslint-disable-next-line max-len
describe('src/module/sw-extension/component/sw-extension-my-extensions-listing-controls', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-extension-my-extensions-listing-controls'), {
            stubs: {
                'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': await Shopware.Component.build('sw-field-error'),
                'sw-select-field': await Shopware.Component.build('sw-select-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-icon': true
            }
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should emit an event when clicking the switch', async () => {
        wrapper = await createWrapper();

        /** @type Wrapper */
        const switchField = wrapper.find('.sw-field--switch input[type="checkbox"]');
        await switchField.setChecked();

        const emittedEvent = wrapper.emitted()['update:active-state'];
        expect(emittedEvent).toBeTruthy();
    });

    it('should emit an event selecting a different option', async () => {
        wrapper = await createWrapper();

        /** @type Wrapper */
        const allSortingOptions = wrapper.findAll('option');
        const sortingOption = allSortingOptions.at(2);

        await sortingOption.setSelected();

        const emittedEvent = wrapper.emitted()['update:sorting-option'];
        expect(emittedEvent).toBeTruthy();
    });
});
