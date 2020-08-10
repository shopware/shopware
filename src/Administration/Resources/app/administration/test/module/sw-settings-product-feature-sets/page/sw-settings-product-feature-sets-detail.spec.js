import { createLocalVue, shallowMount, Wrapper } from '@vue/test-utils';

import 'src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail';
import 'src/app/component/base/sw-card';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-textarea-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

describe('src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail', () => {
    let wrapper;

    const classes = {
        componentRoot: 'sw-settings-product-feature-sets-detail',
        fieldLabel: 'sw-field__label'
    };

    const components = {
        nameField: 'sw-text-field',
        descriptionField: 'sw-textarea-field'
    };

    const text = {
        labelNameField: 'sw-settings-product-feature-sets.detail.labelName',
        placeholderNameField: 'sw-settings-product-feature-sets.detail.placeholderName',
        labelDescriptionField: 'sw-settings-product-feature-sets.detail.labelDescription',
        placeholderDescriptionField: 'sw-settings-product-feature-sets.detail.placeholderDescription'
    };

    const detailPage = (additionalOptions = {}) => {
        const localVue = createLocalVue();

        localVue.directive('tooltip', {});

        return shallowMount(Shopware.Component.build('sw-settings-product-feature-sets-detail'), {
            localVue,
            stubs: {
                'sw-page': true,
                'sw-button-process': true,
                'sw-card-view': true,
                'sw-language-info': true,
                'sw-card': Shopware.Component.build('sw-card'),
                'sw-field': Shopware.Component.build('sw-field'),
                'sw-text-field': Shopware.Component.build('sw-text-field'),
                'sw-textarea-field': Shopware.Component.build('sw-textarea-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': true,
                i18n: true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $te: (translationPath) => translationPath,
                $device: {
                    onResize: () => {},
                    getSystemKey: () => {}
                }
            },
            propsData: {
                productFeatureSet: {
                    id: null,
                    name: null,
                    description: null,
                    features: [
                        {}
                    ]
                }
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => Promise.resolve()
                    })
                },
                validationService: {}
            },
            ...additionalOptions
        });
    };

    /*
     * Workaround, since the current vue-test-utils version doesn't support get()
     *
     * @see https://vue-test-utils.vuejs.org/api/wrapper/#get
     */
    const findSecure = (wrapperEl, findArg) => {
        const el = wrapperEl.find(findArg);

        if (el instanceof Wrapper) {
            return el;
        }

        throw new Error(`Could not find element ${findArg}.`);
    };

    beforeEach(() => {
        wrapper = detailPage();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be able to instantiate', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('has the correct class', () => {
        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it('should show the name field', () => {
        const root = findSecure(wrapper, `.${classes.componentRoot}`);
        const nameField = findSecure(root, { name: components.nameField });
        const nameFieldLabel = findSecure(nameField, `.${classes.fieldLabel}`);

        expect(nameFieldLabel.text()).toEqual(text.labelNameField);
        expect(nameField.props().placeholder).toEqual(text.placeholderNameField);
    });

    it('should show the description field', () => {
        const root = findSecure(wrapper, `.${classes.componentRoot}`);
        const descriptionField = findSecure(root, { name: components.descriptionField });
        const descriptionFieldLabel = findSecure(descriptionField, `.${classes.fieldLabel}`);

        expect(descriptionFieldLabel.text()).toEqual(text.labelDescriptionField);
        expect(descriptionField.props().placeholder).toEqual(text.placeholderDescriptionField);
    });
});
