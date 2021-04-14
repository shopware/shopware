import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation-inputs';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/form/sw-gtc-checkbox';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-select-rating';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/sw-checkbox-field';

describe('src/module/sw-extension/component/sw-ratings/sw-extension-review-creation', () => {
    /** @type Wrapper */
    let wrapper;

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-extension-review-creation'), {
            propsData: {
                extension: {}
            },
            provide: {
                validationService: {},
                extensionStoreActionService: {
                    rateExtension: jest.fn()
                }
            },
            computed: {
                installedVersion() {
                    return '1.0.0';
                }
            },
            stubs: {
                'sw-extension-review-creation-inputs': Shopware.Component.build('sw-extension-review-creation-inputs'),
                'sw-text-field': Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-extension-select-rating': Shopware.Component.build('sw-extension-select-rating'),
                'sw-extension-rating-stars': Shopware.Component.build('sw-extension-rating-stars'),
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-icon': true,
                'sw-textarea-field': {
                    template: '<textarea></textarea>'
                },
                'sw-gtc-checkbox': Shopware.Component.build('sw-gtc-checkbox'),
                'sw-button': Shopware.Component.build('sw-button'),
                'sw-button-process': Shopware.Component.build('sw-button-process')
            }
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should enable the button when the gtc are accepted', async () => {
        wrapper = createWrapper();

        const submitButton = wrapper.find('.sw-button.sw-button--primary');
        expect(submitButton.attributes('disabled')).toBe('disabled');

        const gtcCheckbox = wrapper.find('input[type="checkbox"]');

        await gtcCheckbox.setChecked();
        expect(gtcCheckbox.element.checked).toBe(true);

        expect(submitButton.attributes('disabled')).toBe(undefined);
    });

    it('should set toc to false and disable button when clicking the \'cancel\' button', async () => {
        wrapper = createWrapper();

        const submitButton = wrapper.find('.sw-button.sw-button--primary');
        expect(submitButton.attributes('disabled')).toBe('disabled');

        const gtcCheckbox = wrapper.find('input[type="checkbox"]');

        await gtcCheckbox.setChecked();

        expect(gtcCheckbox.element.checked).toBe(true);

        expect(submitButton.attributes('disabled')).toBe(undefined);

        const cancelButton = wrapper.find('.sw-extension-review-creation__buttons .sw-button');
        await cancelButton.trigger('click');

        expect(submitButton.attributes('disabled')).toBe('disabled');

        expect(gtcCheckbox.element.checked).toBe(false);
    });

    it('should make an api request', async () => {
        wrapper = createWrapper();
        const gtcCheckbox = wrapper.find('input[type="checkbox"]');

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
