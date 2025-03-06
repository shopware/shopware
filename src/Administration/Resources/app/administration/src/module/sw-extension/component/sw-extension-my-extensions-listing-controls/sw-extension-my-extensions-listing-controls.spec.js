import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-extension-my-extensions-listing-controls', {
            sync: true,
        }),
    );
}

/**
 * @sw-package checkout
 */
// eslint-disable-next-line max-len
describe('src/module/sw-extension/component/sw-extension-my-extensions-listing-controls', () => {
    it('should emit an event when clicking the switch', async () => {
        const wrapper = await createWrapper();

        const switchField = wrapper.find('.mt-switch input[type="checkbox"]');
        await switchField.setChecked();

        const emittedEvent = wrapper.emitted()['update:active-state'];
        expect(emittedEvent).toBeTruthy();
    });

    it('should emit an event selecting a different option', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.selectedSortingOption).toBe('updated-at');

        await selectMtSelectOptionByText(wrapper, 'sw-extension.my-extensions.listing.controls.filterOptions.name-asc', '.mt-select__selection');

        expect(wrapper.vm.selectedSortingOption).toBe('name-asc');
        expect(wrapper.emitted()).toHaveProperty('update:sorting-option');
    });
});
