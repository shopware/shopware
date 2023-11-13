import { mount } from '@vue/test-utils_v3';

describe('src/module/sw-settings-listing/component/sw-settings-listing-option-general-info', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-settings-listing-option-general-info', {
            sync: true,
        }), {
            props: {
                sortingOption: {
                    label: 'Price descending',
                },
                isDefaultSorting: false,
            },
            global: {
                provide: {
                    validationService: {},
                },
                directives: {
                    tooltip() {},
                },
                stubs: {
                    'sw-card': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-field': await wrapTestComponent('sw-field'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                },
            },
        });
    }

    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('is a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the correct name', async () => {
        const textField = wrapper.find('.sw-field--text input');

        expect(textField.element.value).toBe('Price descending');
    });

    it('should display the correct active state', async () => {
        const switchField = wrapper.find('.sw-field--switch input');
        const isActive = switchField.element.value;

        expect(isActive).toBe('on');
    });

    it('should not disable active state switch on normal product sortings', async () => {
        const switchField = wrapper.find('.sw-field--switch input');
        const isDisabled = switchField.attributes('disabled');

        expect(isDisabled).toBeUndefined();
    });

    it('should disable active state switch on default sortings', async () => {
        await wrapper.setProps({ isDefaultSorting: true });

        const switchField = wrapper.find('.sw-field--switch input');
        const isDisabled = switchField.attributes('disabled');

        expect(isDisabled).toBeDefined();
    });
});
