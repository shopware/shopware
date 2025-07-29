import { mount } from '@vue/test-utils';

/**
 * @sw-package inventory
 */

// Turn off known errors
import { unknownOptionError } from 'test/_helper_/allowedErrors';

global.allowedErrors = [
    ...global.allowedErrors,
    unknownOptionError,
];

describe('src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-modal', () => {
    const classes = {
        componentRoot: 'sw-settings-product-feature-sets__modal',
        optionsContainer: 'sw-settings-product-feature-sets-modal__options',
        propertyListToolbar: 'sw-product-feature-set-modal-property-list__toolbar',
        propertyListSearchField: 'sw-simple-search-field',
        propertyListHeader: 'sw-data-grid__header',
        propertyList: 'sw-data-grid',
        propertyListCellContent: 'sw-data-grid__cell-content',
        customFieldListToolbar: 'sw-product-feature-set-modal-custom-field-list__toolbar',
        customFieldListSearchField: 'sw-simple-search-field',
        customFieldListHeader: 'sw-data-grid__header',
        customFieldList: 'sw-data-grid',
        customFieldListCellContent: 'sw-data-grid__cell-content',
        productInformationListHeader: 'sw-data-grid__header',
        productInformationList: 'sw-data-grid',
        productInformationListCellContent: 'sw-data-grid__cell-content',
    };

    const text = {
        propertyListNameHeader: 'sw-settings-product-feature-sets.modal.textPropertyLabel',
        customFieldListNameHeader: 'sw-settings-product-feature-sets.modal.labelName',
        customFieldListTypeHeader: 'sw-settings-product-feature-sets.valuesCard.labelType',
        productInformationListNameHeader: 'sw-settings-product-feature-sets.modal.labelName',
    };

    function returnPageConfigDataObject(config) {
        return {
            showPageOne: false,
            showCustomField: false,
            showPropertyGroups: false,
            showProductInfo: false,
            ...config,
        };
    }

    async function createWrapper(additionalOptions = {}, productFeatureSet = {}) {
        return mount(
            await wrapTestComponent('sw-settings-product-feature-sets-modal', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    stubs: {
                        'sw-modal': {
                            template: `
                            <div class="sw-modal">
                                <slot name="modal-header"></slot>
                                <slot />
                                <slot name="modal-footer"></slot>
                            </div>
                        `,
                        },
                        'sw-radio-field': await wrapTestComponent('sw-radio-field', {
                            sync: true,
                        }),
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                        'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                        'sw-text-field': await wrapTestComponent('sw-text-field'),
                        'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                        'sw-data-grid-skeleton': true,
                        i18n: true,
                        'sw-pagination': true,
                        'sw-help-text': true,
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                        'sw-field-error': true,
                        'sw-checkbox-field': true,
                        'sw-context-menu-item': true,
                        'sw-context-button': true,
                        'sw-data-grid-settings': true,
                        'sw-data-grid-column-boolean': true,
                        'sw-data-grid-inline-edit': true,
                        'router-link': true,
                        'sw-field-copyable': true,
                        'sw-contextual-field': true,
                        'sw-provide': true,
                    },
                    provide: {
                        shortcutService: {
                            startEventListener: () => {},
                            stopEventListener: () => {},
                        },
                        repositoryFactory: {
                            create: (entity) => ({
                                search: () => {
                                    if (entity === 'custom_field') {
                                        return Promise.resolve(
                                            new Array(10).fill(null).map((_, i) => ({
                                                id: `cf-id-${i}`,
                                                name: `cf-${i}`,
                                                config: { label: `cf-label-${i}` },
                                            })),
                                        );
                                    }

                                    if (entity === 'property_group') {
                                        return Promise.resolve(
                                            new Array(10)
                                                .fill(null)
                                                .map((_, i) => ({ id: `prop-id-${i}`, name: `prop-${i}` })),
                                        );
                                    }

                                    return Promise.resolve([]);
                                },
                            }),
                        },
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
                        ...productFeatureSet,
                    },
                },
            },
        );
    }

    it('should be able to instantiate', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('has the correct class', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it('contains the options container', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData(returnPageConfigDataObject({ showPageOne: true }));
        await flushPromises();

        const optionsContainer = wrapper.findComponent('.sw-settings-product-feature-sets-modal__options');

        expect(optionsContainer.props().options).toHaveLength(4);

        // Check wether all possible feature types are shown
        [
            'property',
            'customField',
            'product',
            'referencePrice',
        ].forEach((type) => {
            expect(optionsContainer.props().options.filter((option) => option.value === type)).toHaveLength(1);
        });
    });

    it('contains the custom field list', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData(returnPageConfigDataObject({ showCustomField: true }));
        await flushPromises();

        const root = wrapper.get(`.${classes.componentRoot}`);

        [
            classes.customFieldListToolbar,
            classes.customFieldListSearchField,
            classes.customFieldListHeader,
            classes.customFieldList,
        ].forEach((className) => {
            root.get(`.${className}`);
        });

        const customFieldListHeader = root.get(`.${classes.customFieldListHeader}`);
        const customFieldListHeaderContent = customFieldListHeader.findAll(`.${classes.customFieldListCellContent}`);

        expect(customFieldListHeaderContent.at(1).text()).toEqual(text.customFieldListNameHeader);
        expect(customFieldListHeaderContent.at(2).text()).toEqual(text.customFieldListTypeHeader);
    });

    it('contains the property group list', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData(returnPageConfigDataObject({ showPropertyGroups: true }));
        await flushPromises();

        const root = wrapper.get(`.${classes.componentRoot}`);

        [
            classes.propertyListToolbar,
            classes.propertyListSearchField,
            classes.propertyListHeader,
            classes.propertyList,
        ].forEach((className) => {
            root.get(`.${className}`);
        });

        const propertyListHeader = root.get(`.${classes.propertyListHeader}`);
        const propertyListHeaderContent = propertyListHeader.findAll(`.${classes.propertyListCellContent}`);

        expect(propertyListHeaderContent.at(1).text()).toEqual(text.propertyListNameHeader);
    });

    it('contains the product information list', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData(returnPageConfigDataObject({ showCustomField: true }));
        await flushPromises();

        const root = wrapper.get(`.${classes.componentRoot}`);

        [
            classes.productInformationListHeader,
            classes.productInformationList,
            classes.productInformationListCellContent,
        ].forEach((className) => {
            root.get(`.${className}`);
        });

        const propertyListHeader = root.get(`.${classes.propertyListHeader}`);
        const propertyListHeaderContent = propertyListHeader.findAll(`.${classes.propertyListCellContent}`);

        expect(propertyListHeaderContent.at(1).text()).toEqual(text.productInformationListNameHeader);
    });

    it('should select items on the current custom field grid', async () => {
        const wrapper = await createWrapper();
        const selectItemMock = jest.fn();

        await wrapper.setData({
            ...returnPageConfigDataObject({ showCustomField: true }),
            customFields: [
                { id: 'cf-id-1', name: 'cf-1', config: { label: 'cf-label-1' } },
                { id: 'cf-id-2', name: 'cf-2', config: { label: 'cf-label-2' } },
                { id: 'cf-id-3', name: 'cf-3', config: { label: 'cf-label-3' } },
            ],
        });

        await flushPromises();

        wrapper.vm.selectedFeatures = new Map([
            [
                1,
                { 'cf-id-1': { id: 'cf-id-1' }, 'cf-id-3': { id: 'cf-id-3' } },
            ],
        ]);

        wrapper.vm.$refs.customFieldGrid.selectItem = selectItemMock;

        await wrapper.vm.applySelectionsToActiveGrid();

        expect(selectItemMock).toHaveBeenCalledTimes(2);
        expect(selectItemMock).toHaveBeenCalledWith(true, { id: 'cf-id-1', name: 'cf-1', config: { label: 'cf-label-1' } });
        expect(selectItemMock).toHaveBeenCalledWith(true, { id: 'cf-id-3', name: 'cf-3', config: { label: 'cf-label-3' } });
    });

    it('should select items on the current property group grid', async () => {
        const wrapper = await createWrapper();
        const selectItemMock = jest.fn();

        await wrapper.setData({
            ...returnPageConfigDataObject({ showPropertyGroups: true }),
            propertyGroups: [
                { id: 'prop-id-1', name: 'prop-1' },
                { id: 'prop-id-2', name: 'prop-2' },
                { id: 'prop-id-3', name: 'prop-3' },
            ],
        });

        await flushPromises();

        wrapper.vm.selectedFeatures = new Map([
            [
                1,
                { 'prop-id-2': { id: 'prop-id-2' } },
            ],
        ]);

        wrapper.vm.$refs.propertyGroupGrid.selectItem = selectItemMock;

        await wrapper.vm.applySelectionsToActiveGrid();

        expect(selectItemMock).toHaveBeenCalledTimes(1);
        expect(selectItemMock).toHaveBeenCalledWith(true, { id: 'prop-id-2', name: 'prop-2' });
    });
});
