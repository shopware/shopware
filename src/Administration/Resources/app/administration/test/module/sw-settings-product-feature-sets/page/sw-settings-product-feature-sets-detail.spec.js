import { createLocalVue, shallowMount, Wrapper } from '@vue/test-utils';

import 'src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail';
import 'src/app/component/base/sw-card';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-textarea-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

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

const detailPage = (additionalOptions = {}, privileges = []) => {
    const localVue = createLocalVue();

    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-product-feature-sets-detail'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
<div class="sw-page">
    <slot name="smart-bar-actions"></slot>
    <slot name="content"></slot>
</div>
                    `
            },
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
            'sw-settings-product-feature-sets-values-card': true,
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
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
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

describe('src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail', () => {
    let wrapper;

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

    it('should be able to instantiate', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('has the correct class', async () => {
        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it('should show the name field', async () => {
        const root = findSecure(wrapper, `.${classes.componentRoot}`);
        const nameField = findSecure(root, { name: components.nameField });
        const nameFieldLabel = findSecure(nameField, `.${classes.fieldLabel}`);

        expect(nameFieldLabel.text()).toEqual(text.labelNameField);
        expect(nameField.props().placeholder).toEqual(text.placeholderNameField);
    });

    it('should show the description field', async () => {
        const root = findSecure(wrapper, `.${classes.componentRoot}`);
        const descriptionField = findSecure(root, { name: components.descriptionField });
        const descriptionFieldLabel = findSecure(descriptionField, `.${classes.fieldLabel}`);

        expect(descriptionFieldLabel.text()).toEqual(text.labelDescriptionField);
        expect(descriptionField.props().placeholder).toEqual(text.placeholderDescriptionField);
    });

    it('should have all fields disabled when user has no acl rights', async () => {
        await wrapper.setData({
            productFeatureSet: {
                id: '1a2b3c',
                name: null,
                description: null,
                features: [
                    {}
                ]
            }
        });

        const saveButton = wrapper.find('.sw-settings-currency-detail__save-action');
        const fieldName = wrapper.find('.sw-settings-product-feature-sets-detail__name');
        const fieldDescription = wrapper.find('.sw-settings-product-feature-sets-detail__description');
        const productFeatureSetsValuesCard = wrapper.find('.sw-settings-product-feature-sets-detail__tax-rule-grid');

        expect(saveButton.attributes().disabled).toBe('true');
        expect(fieldName.vm.$attrs.disabled).toBe(true);
        expect(fieldDescription.vm.$attrs.disabled).toBe(true);
        expect(productFeatureSetsValuesCard.attributes().allowedit).toBeUndefined();
    });

    it('should have all fields enabled when user has acl rights', async () => {
        wrapper = await detailPage({}, [
            'product_feature_sets.editor'
        ]);

        await wrapper.setData({
            productFeatureSet: {
                id: '1a2b3c',
                name: null,
                description: null,
                features: [
                    {}
                ]
            }
        });

        const saveButton = wrapper.find('.sw-settings-currency-detail__save-action');
        const fieldName = wrapper.find('.sw-settings-product-feature-sets-detail__name');
        const fieldDescription = wrapper.find('.sw-settings-product-feature-sets-detail__description');
        const productFeatureSetsValuesCard = wrapper.find('.sw-settings-product-feature-sets-detail__tax-rule-grid');

        expect(saveButton.attributes().disabled).toBeUndefined();
        expect(fieldName.vm.$attrs.disabled).toBe(false);
        expect(fieldDescription.vm.$attrs.disabled).toBe(false);
        expect(productFeatureSetsValuesCard.attributes().allowedit).toBe('true');
    });
});
