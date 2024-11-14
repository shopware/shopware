import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-extension-my-extensions-listing-controls', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
                    'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                    'sw-select-field': {
                        template: '<div><slot /></div>',
                    },
                    'sw-base-field': true,
                    'sw-field-error': true,
                },
            },
        },
    );
}

/**
 * @package checkout
 */
// eslint-disable-next-line max-len
describe('src/module/sw-extension/component/sw-extension-my-extensions-listing-controls', () => {
    it('should emit an event when clicking the switch', async () => {
        const wrapper = await createWrapper();

        const switchField = wrapper.find('.sw-field--switch input[type="checkbox"]');
        await switchField.setChecked();

        const emittedEvent = wrapper.emitted()['update:active-state'];
        expect(emittedEvent).toBeTruthy();
    });

    it('should emit an event selecting a different option', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.selectedSortingOption).toBe('updated-at');

        const allSortingOptions = wrapper.findAll('option');
        const sortingOption = allSortingOptions.at(2);

        await wrapper.setData({
            selectedSortingOption: sortingOption.element.value,
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectedSortingOption).toEqual(sortingOption.element.value);
        expect(wrapper.emitted()).toHaveProperty('update:sorting-option');
    });
});
