import { mount } from '@vue/test-utils';

/**
 * @sw-package inventory
 */
describe('src/module/sw-settings-listing/component/sw-settings-listing-option-general-info', () => {
    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-settings-listing-option-general-info', {
                sync: true,
            }),
            {
                props: {
                    sortingOption: {
                        label: 'Price descending',
                        key: 'price-desc',
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
                        'mt-card': {
                            template: '<div><slot></slot></div>',
                        },
                        'sw-container': {
                            template: '<div><slot></slot></div>',
                        },
                        'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-block-field': await wrapTestComponent('sw-block-field'),
                        'sw-field-error': await wrapTestComponent('sw-field-error'),
                        'sw-field-copyable': true,
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                        'sw-help-text': true,
                    },
                },
            },
        );
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
        const textField = wrapper.find('.sw-settings-listing-option-general-info__field-name input');

        expect(textField.element.value).toBe('Price descending');
    });

    it('should display name error', async () => {
        await wrapper.setProps({ labelError: {} });

        expect(wrapper.find('.sw-settings-listing-option-general-info__field-name .mt-field__error').exists()).toBe(true);
    });

    it('should display the correct technical name', async () => {
        const textField = wrapper.find('.sw-settings-listing-option-general-info__field-technical-name input');

        expect(textField.element.value).toBe('price-desc');
    });

    it('should display technical name error', async () => {
        await wrapper.setProps({ technicalNameError: {} });

        expect(
            wrapper.find('.sw-settings-listing-option-general-info__field-technical-name .mt-field__error').exists(),
        ).toBe(true);
    });

    it('should display the correct active state', async () => {
        const switchField = wrapper.find('.sw-settings-listing-option-general-info__field-active input');
        const isActive = switchField.element.value;

        expect(isActive).toBe('on');
    });

    it('should not disable active state switch on normal product sortings', async () => {
        const switchField = wrapper.find('.sw-settings-listing-option-general-info__field-active input');
        const isDisabled = switchField.attributes('disabled');

        expect(isDisabled).toBeUndefined();
    });

    it('should disable active state switch on default sortings', async () => {
        await wrapper.setProps({ isDefaultSorting: true });

        const switchField = wrapper.find('.sw-settings-listing-option-general-info__field-active input');
        const isDisabled = switchField.attributes('disabled');

        expect(isDisabled).toBeDefined();
    });

    it('should disable untranslated fields on locked product sorting', async () => {
        await wrapper.setProps({
            sortingOption: {
                label: 'Top result',
                key: 'score',
                fields: [
                    {
                        field: '_score',
                        naturalSorting: 0,
                        order: 'desc',
                        priority: 1,
                    },
                ],
                locked: true,
            },
        });

        const untranslatedFields = [
            '.sw-settings-listing-option-general-info__field-technical-name input',
            '.sw-settings-listing-option-general-info__field-active input',
        ];

        untranslatedFields.forEach((field) => {
            const fieldInput = wrapper.find(field);
            const isDisabled = fieldInput.attributes('disabled');

            expect(isDisabled).toBeDefined();
        });
    });
});
