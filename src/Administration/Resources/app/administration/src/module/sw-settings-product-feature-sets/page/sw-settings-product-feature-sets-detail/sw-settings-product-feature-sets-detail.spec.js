import { mount } from '@vue/test-utils_v3';

const classes = {
    componentRoot: 'sw-settings-product-feature-sets-detail',
    fieldLabel: 'sw-field__label',
};

const text = {
    labelNameField: 'sw-settings-product-feature-sets.detail.labelName',
    placeholderNameField: 'sw-settings-product-feature-sets.detail.placeholderName',
    labelDescriptionField: 'sw-settings-product-feature-sets.detail.labelDescription',
    placeholderDescriptionField: 'sw-settings-product-feature-sets.detail.placeholderDescription',
};

const detailPage = async (additionalOptions = {}, privileges = []) => {
    return mount(await wrapTestComponent('sw-settings-product-feature-sets-detail', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-page': {
                    template: `
<div class="sw-page">
    <slot name="smart-bar-actions"></slot>
    <slot name="content"></slot>
</div>
                    `,
                },
                'sw-button-process': true,
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-language-info': true,
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-ignore-class': true,
                'sw-field': await wrapTestComponent('sw-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field', {
                    sync: true,
                }),
                'sw-textarea-field': await wrapTestComponent('sw-textarea-field', {
                    sync: true,
                }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-settings-product-feature-sets-values-card': true,
                'sw-extension-component-section': true,
                'sw-skeleton': true,
                i18n: true,
            },
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                repositoryFactory: {
                    create: () => ({
                        create: () => Promise.resolve(),
                    }),
                },
                validationService: {},
            },
            ...additionalOptions,
        },
        props: {
            productFeatureSet: {
                id: null,
                name: null,
                description: null,
                features: [
                    {},
                ],
            },
        },
    });
};

describe('src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await detailPage();
        await flushPromises();
    });

    it('should be able to instantiate', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('has the correct class', async () => {
        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it('should show the name field', async () => {
        const root = wrapper.get(`.${classes.componentRoot}`);
        const nameField = root.findComponent('.sw-field--text');
        const nameFieldLabel = nameField.get(`.${classes.fieldLabel}`);

        expect(nameFieldLabel.text()).toEqual(text.labelNameField);
        expect(nameField.props().placeholder).toEqual(text.placeholderNameField);
    });

    it('should show the description field', async () => {
        const root = wrapper.get(`.${classes.componentRoot}`);
        const descriptionField = root.findComponent('.sw-field--textarea');
        const descriptionFieldLabel = descriptionField.get(`.${classes.fieldLabel}`);

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
                    {},
                ],
            },
        });

        const saveButton = wrapper.get('.sw-settings-currency-detail__save-action');
        const fieldName = wrapper.findComponent('.sw-settings-product-feature-sets-detail__name');
        const fieldDescription = wrapper.findComponent('.sw-settings-product-feature-sets-detail__description');
        const productFeatureSetsValuesCard = wrapper.findComponent('.sw-settings-product-feature-sets-detail__tax-rule-grid');

        expect(saveButton.attributes().disabled).toBe('true');
        expect(fieldName.vm.$attrs.disabled).toBe(true);
        expect(fieldDescription.vm.$attrs.disabled).toBe(true);
        expect(productFeatureSetsValuesCard.attributes()['allow-edit']).toBeUndefined();
    });

    it('should have all fields enabled when user has acl rights', async () => {
        wrapper = await detailPage({}, [
            'product_feature_sets.editor',
        ]);

        await wrapper.setData({
            productFeatureSet: {
                id: '1a2b3c',
                name: null,
                description: null,
                features: [
                    {},
                ],
            },
        });

        const saveButton = wrapper.get('.sw-settings-currency-detail__save-action');
        const fieldName = wrapper.findComponent('.sw-settings-product-feature-sets-detail__name');
        const fieldDescription = wrapper.findComponent('.sw-settings-product-feature-sets-detail__description');
        const productFeatureSetsValuesCard = wrapper.findComponent('.sw-settings-product-feature-sets-detail__tax-rule-grid');

        expect(saveButton.attributes().disabled).toBeUndefined();
        expect(fieldName.vm.$attrs.disabled).toBe(false);
        expect(fieldDescription.vm.$attrs.disabled).toBe(false);
        expect(productFeatureSetsValuesCard.attributes()['allow-edit']).toBe('true');
    });
});
