import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-review-creation', () => {
    beforeAll(() => {
        if (Shopware.State.get('shopwareExtensions')) {
            Shopware.State.unregisterModule('shopwareExtensions');
        }

        Shopware.State.registerModule('shopwareExtensions', {
            namespaced: true,
            state: {
                myExtensions: {
                    data: [
                        {
                            name: 'Test',
                            installedAt: null,
                            version: '1.0.0',
                        },
                    ],
                },
            },
        });
    });

    async function createWrapper() {
        return mount(await wrapTestComponent('sw-extension-review-creation', { sync: true }), {
            global: {
                provide: {
                    validationService: {},
                    extensionStoreActionService: {
                        rateExtension: jest.fn(),
                    },
                },
                stubs: {
                    'sw-extension-review-creation-inputs': await wrapTestComponent('sw-extension-review-creation-inputs', { sync: true }),
                    'sw-text-field': await wrapTestComponent('sw-text-field', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                    'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                    'sw-extension-select-rating': await wrapTestComponent('sw-extension-select-rating', { sync: true }),
                    'sw-extension-rating-stars': await wrapTestComponent('sw-extension-rating-stars', { sync: true }),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                    'sw-icon': true,
                    'sw-textarea-field': {
                        template: '<textarea></textarea>',
                    },
                    'sw-gtc-checkbox': await wrapTestComponent('sw-gtc-checkbox', { sync: true }),
                    'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                    'sw-button-process': await wrapTestComponent('sw-button-process', { sync: true }),
                    'sw-external-link': await wrapTestComponent('sw-external-link', { sync: true }),
                    'sw-loader': true,
                },
            },
            props: {
                extension: {
                    name: 'Test',
                },
            },
        });
    }

    it('should enable the button when the gtc are accepted', async () => {
        const wrapper = await createWrapper();

        const submitButton = wrapper.get('.sw-extension-review-creation__submit');
        expect(submitButton.attributes('disabled')).toBeDefined();

        const gtcCheckbox = wrapper.get('input[type="checkbox"]');
        await gtcCheckbox.setChecked();
        expect(gtcCheckbox.element.checked).toBe(true);

        expect(submitButton.attributes('disabled')).toBeUndefined();
    });

    it('should make an api request', async () => {
        const wrapper = await createWrapper();

        const gtcCheckbox = wrapper.get('input[type="checkbox"]');
        await gtcCheckbox.setChecked();
        expect(gtcCheckbox.element.checked).toBe(true);

        // input components
        const descriptionTextarea = wrapper.find('textarea');
        const titleInput = wrapper.find('input[type="text"]');
        const star = wrapper.find('button.sw-extension-rating-stars__star');

        // creating review
        await titleInput.setValue('bad app');
        await descriptionTextarea.setValue('not very good');
        await star.trigger('click');

        // submitting review
        const submitButton = wrapper.find('.sw-button.sw-button--primary');
        await submitButton.trigger('click');

        expect(wrapper.vm.extensionStoreActionService.rateExtension).toHaveBeenCalled();
    });
});
