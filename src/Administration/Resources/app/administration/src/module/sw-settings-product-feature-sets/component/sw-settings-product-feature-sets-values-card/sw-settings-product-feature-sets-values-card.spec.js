import { mount } from '@vue/test-utils';

describe('src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-values-card', () => {
    let wrapper;

    const classes = {
        componentRoot: 'sw-settings-product-feature-sets-values-card',
        valueListToolbar: 'sw-product-feature-set__toolbar',
        valueListDeleteButton: 'sw-product-feature-set__delete-button',
        valueListAddButton: 'sw-product-feature-set__add-button',
        valueList: 'sw-data-grid',
        valueListHeader: 'sw-data-grid__header',
        valueListBody: 'sw-data-grid__body',
        valueListRow: 'sw-data-grid__row',
        valueListCellContent: 'sw-data-grid__cell-content',
        valueListCellName: 'sw-data-grid__cell--name',
        valueListPositionColumn: 'sw-data-grid-column-position',
        valueListPositionButtons: 'sw-data-grid-column-position__group',
        valueListFirstRow: 'sw-data-grid__row--0',
        valueListSecondRow: 'sw-data-grid__row--1',
        valueListThirdRow: 'sw-data-grid__row--2',
    };

    const text = {
        labelCreateNew: 'sw-settings-product-feature-sets.valuesCard.labelCreateNew',
        labelValue: 'sw-settings-product-feature-sets.valuesCard.labelValue',
        labelType: 'sw-settings-product-feature-sets.valuesCard.labelType',
        labelPosition: 'sw-settings-product-feature-sets.valuesCard.labelPosition',
        labelReferencePriceType: 'sw-settings-product-feature-sets.modal.label.referencePrice',
        labelReferencePriceValue: 'sw-settings-product-feature-sets.modal.textReferencePriceLabel',
        labelReferencePrice: 'sw-settings-product-feature-sets.modal.label.referencePrice',
        labelDescription: 'sw-settings-product-feature-sets.modal.label.description',
        labelName: 'sw-settings-product-feature-sets.modal.label.name',
    };

    const valuesCard = async (additionalOptions = {}, additionalProps = {}) => {
        return mount(await wrapTestComponent('sw-settings-product-feature-sets-values-card', {
            sync: true,
        }), {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                    'sw-container': true,
                    'sw-simple-search-field': true,
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-icon': true,
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                        sync: true,
                    }),
                    'sw-loader': true,
                    'sw-checkbox-field': true,
                    'sw-data-grid-column-position': await wrapTestComponent('sw-data-grid-column-position', {
                        sync: true,
                    }),
                    'sw-button-group': true,
                    'sw-extension-component-section': true,
                    i18n: true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.reject(),
                        }),
                        search: () => {
                        },
                    },
                },
                ...additionalOptions,
            },
            props: {
                isLoading: false,
                productFeatureSet: {
                    id: '21605c15655f441f9e1275e2a2f2e1d1',
                    name: '4d4c4b4e-a52a-4756-a93b-2c5345224389',
                    description: 'c67c181d-f883-4e3d-bce0-97ed913927fe',
                    features: [
                        {
                            type: 'referencePrice',
                            id: null,
                            name: null,
                            position: 0,
                        },
                        {
                            type: 'product',
                            id: null,
                            name: 'description',
                            position: 1,
                        },
                        {
                            type: 'product',
                            id: null,
                            name: 'name',
                            position: 2,
                        },
                    ],
                },
                ...additionalProps,
            },
        });
    };

    const getReferencePrice = (props) => {
        return props.productFeatureSet.features.find(feature => feature.type === 'referencePrice');
    };

    beforeEach(async () => {
        wrapper = await valuesCard();
        await flushPromises();
    });

    it('should be able to instantiate', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('has the correct class', async () => {
        expect(wrapper.get('.sw-card').classes()).toContain(classes.componentRoot);
    });

    it('shows a list of features', async () => {
        const root = wrapper.get(`.${classes.componentRoot}`);
        const list = root.get(`.${classes.valueList}`);
        const header = list.get(`.${classes.valueListHeader}`);
        const body = list.get(`.${classes.valueListBody}`);
        const firstRow = body.get(`.${classes.valueListFirstRow}`);

        const headerCellContent = {
            selectAll: '',
            value: text.labelValue,
            type: text.labelType,
            position: text.labelPosition,
        };
        const headerCells = header.findAll(`.${classes.valueListCellContent}`);

        expect(header.exists()).toBeTruthy();
        expect(headerCells).toHaveLength(Object.values(headerCellContent).length);

        // Check if all column headers are present
        Object.values(headerCellContent).forEach((value) => {
            expect(headerCells.filter((cell) => {
                return cell.text() === value;
            })).toHaveLength(1);
        });

        const bodyCells = firstRow.findAll(`.${classes.valueListCellContent}`);

        expect(bodyCells).toHaveLength(Object.values(headerCellContent).length);

        [
            '',
            text.labelReferencePriceType,
            text.labelReferencePriceValue,
            '',
        ].forEach((value, index) => {
            expect(bodyCells.at(index).text()).toEqual(value);
        });
    });

    it('correctly propagates changes when a position is updated', async () => {
        const root = wrapper.get(`.${classes.componentRoot}`);
        const list = root.get(`.${classes.valueList}`);
        const body = list.get(`.${classes.valueListBody}`);
        const firstRow = body.get(`.${classes.valueListFirstRow}`);
        const secondRow = body.get(`.${classes.valueListSecondRow}`);
        const firstRowPositionButtons = firstRow.get(`.${classes.valueListPositionButtons}`);

        expect(getReferencePrice(wrapper.props()).position).toBe(0);

        // Same check for DOM
        expect(firstRow.get(`.${classes.valueListCellName}`).text()).toEqual(text.labelReferencePrice);
        expect(secondRow.get(`.${classes.valueListCellName}`).text()).toEqual(text.labelDescription);

        await firstRowPositionButtons.find('button.arrow_down').trigger('click');

        expect(getReferencePrice(wrapper.props()).position).toBe(1);

        // Same check for DOM
        expect(secondRow.get(`.${classes.valueListCellName}`).text()).toEqual(text.labelReferencePrice);
        expect(firstRow.get(`.${classes.valueListCellName}`).text()).toEqual(text.labelDescription);
    });

    it('all fields are enabled', async () => {
        const searchField = wrapper.find('sw-simple-search-field-stub');
        const deleteButton = wrapper.find('.sw-product-feature-set__delete-button');
        const addButton = wrapper.find('.sw-product-feature-set__add-button');
        const dataGrid = wrapper.findComponent('.sw-data-grid');
        const columnPositions = wrapper.findAllComponents('.sw-data-grid-column-position');

        expect(searchField.exists()).toBeTruthy();
        expect(deleteButton.exists()).toBeTruthy();
        expect(addButton.exists()).toBeTruthy();
        expect(dataGrid.exists()).toBeTruthy();

        expect(searchField.attributes().disabled).toBeUndefined();
        expect(deleteButton.attributes().disabled).toBeDefined();
        expect(addButton.attributes().disabled).toBeUndefined();
        expect(dataGrid.props().showSelection).toBe(true);

        columnPositions.forEach(columnPosition => {
            expect(columnPosition.props().disabled).toBe(false);
        });
    });

    it('all fields are disabled when prop allowEdit is false', async () => {
        await wrapper.setProps({
            allowEdit: false,
        });

        const searchField = wrapper.find('sw-simple-search-field-stub');
        const deleteButton = wrapper.find('.sw-product-feature-set__delete-button');
        const addButton = wrapper.find('.sw-product-feature-set__add-button');
        const dataGrid = wrapper.findComponent('.sw-data-grid');
        const columnPositions = wrapper.findAllComponents('.sw-data-grid-column-position');

        expect(searchField.exists()).toBeTruthy();
        expect(deleteButton.exists()).toBeTruthy();
        expect(addButton.exists()).toBeTruthy();
        expect(dataGrid.exists()).toBeTruthy();

        expect(searchField.attributes().disabled).toBeDefined();
        expect(deleteButton.attributes().disabled).toBeDefined();
        expect(addButton.attributes().disabled).toBeDefined();
        expect(dataGrid.props().showSelection).toBe(false);

        columnPositions.forEach(columnPosition => {
            expect(columnPosition.props().disabled).toBe(true);
        });
    });

    it('should load feature listing as empty if there are no features', async () => {
        wrapper.unmount();
        await flushPromises();

        wrapper = await valuesCard({}, {
            isLoading: false,
            productFeatureSet: {
                id: '21605c15655f441f9e1275e2a2f2e1d1',
                name: '4d4c4b4e-a52a-4756-a93b-2c5345224389',
                description: 'c67c181d-f883-4e3d-bce0-97ed913927fe',
                features: [],
            },
        });
        await flushPromises();

        const rootEmpty = wrapper.get(`.${classes.componentRoot}.is--empty`);

        expect(wrapper.vm).toBeTruthy();
        expect(rootEmpty.exists()).toBeTruthy();
    });
});
