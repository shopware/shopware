import { shallowMount } from '@vue/test-utils';

import 'src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-values-card';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-button';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/data-grid/sw-data-grid-column-position';

const swDataGrid = Shopware.Component.build('sw-data-grid');
const swDataGridColumnPosition = Shopware.Component.build('sw-data-grid-column-position');

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
        valueListThirdRow: 'sw-data-grid__row--2'
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
        labelName: 'sw-settings-product-feature-sets.modal.label.name'
    };

    const valuesCard = (additionalOptions = {}) => {
        return shallowMount(Shopware.Component.build('sw-settings-product-feature-sets-values-card'), {
            stubs: {
                'sw-card': Shopware.Component.build('sw-card'),
                'sw-container': true,
                'sw-simple-search-field': true,
                'sw-button': Shopware.Component.build('sw-button'),
                'sw-icon': true,
                'sw-data-grid': swDataGrid,
                'sw-loader': true,
                'sw-checkbox-field': true,
                'sw-data-grid-column-position': swDataGridColumnPosition,
                'sw-button-group': true,
                i18n: true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $te: (translationPath) => translationPath,
                $device: {
                    onResize: () => {
                    }
                }
            },
            propsData: {
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
                            position: 0
                        },
                        {
                            type: 'product',
                            id: null,
                            name: 'description',
                            position: 1
                        },
                        {
                            type: 'product',
                            id: null,
                            name: 'name',
                            position: 2
                        }
                    ]
                }
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.reject()
                    }),
                    search: () => {
                    }
                }
            },
            ...additionalOptions
        });
    };

    const getReferencePrice = (props) => {
        return props.productFeatureSet.features.find(feature => feature.type === 'referencePrice');
    };

    beforeEach(() => {
        wrapper = valuesCard();
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
            position: text.labelPosition
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
            ''
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

        expect(getReferencePrice(wrapper.props()).position).toEqual(0);

        // Same check for DOM
        expect(firstRow.get(`.${classes.valueListCellName}`).text()).toEqual(text.labelReferencePrice);
        expect(secondRow.get(`.${classes.valueListCellName}`).text()).toEqual(text.labelDescription);

        await firstRowPositionButtons.find('button.arrow_down').trigger('click');

        expect(getReferencePrice(wrapper.props()).position).toEqual(1);

        // Same check for DOM
        expect(secondRow.get(`.${classes.valueListCellName}`).text()).toEqual(text.labelReferencePrice);
        expect(firstRow.get(`.${classes.valueListCellName}`).text()).toEqual(text.labelDescription);
    });

    it('all fields are enabled', async () => {
        const searchField = wrapper.find('sw-simple-search-field-stub');
        const deleteButton = wrapper.find('.sw-product-feature-set__delete-button');
        const addButton = wrapper.find('.sw-product-feature-set__add-button');
        const dataGrid = wrapper.findComponent(swDataGrid);
        const columnPositions = wrapper.findAllComponents(swDataGridColumnPosition);

        expect(searchField.exists()).toBeTruthy();
        expect(deleteButton.exists()).toBeTruthy();
        expect(addButton.exists()).toBeTruthy();
        expect(dataGrid.exists()).toBeTruthy();

        expect(searchField.attributes().disabled).toBeUndefined();
        expect(deleteButton.attributes().disabled).toBe('disabled');
        expect(addButton.attributes().disabled).toBeUndefined();
        expect(dataGrid.props().showSelection).toBe(true);

        columnPositions.wrappers.forEach(columnPosition => {
            expect(columnPosition.props().disabled).toBe(false);
        });
    });

    it('all fields are disabled when prop allowEdit is false', async () => {
        await wrapper.setProps({
            allowEdit: false
        });

        const searchField = wrapper.find('sw-simple-search-field-stub');
        const deleteButton = wrapper.find('.sw-product-feature-set__delete-button');
        const addButton = wrapper.find('.sw-product-feature-set__add-button');
        const dataGrid = wrapper.findComponent(swDataGrid);
        const columnPositions = wrapper.findAllComponents(swDataGridColumnPosition);

        expect(searchField.exists()).toBeTruthy();
        expect(deleteButton.exists()).toBeTruthy();
        expect(addButton.exists()).toBeTruthy();
        expect(dataGrid.exists()).toBeTruthy();

        expect(searchField.attributes().disabled).toBe('true');
        expect(deleteButton.attributes().disabled).toBe('disabled');
        expect(addButton.attributes().disabled).toBe('disabled');
        expect(dataGrid.props().showSelection).toBe(false);

        columnPositions.wrappers.forEach(columnPosition => {
            expect(columnPosition.props().disabled).toBe(true);
        });
    });
});
