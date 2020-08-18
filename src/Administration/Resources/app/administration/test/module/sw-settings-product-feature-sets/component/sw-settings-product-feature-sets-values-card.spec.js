import { shallowMount, Wrapper } from '@vue/test-utils';

import 'src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-values-card';
import 'src/app/component/base/sw-card';
import 'src/app/component/data-grid/sw-data-grid';

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
        valueListCellContent: 'sw-data-grid__cell-content'
    };

    const text = {
        labelCreateNew: 'sw-settings-product-feature-sets.valuesCard.labelCreateNew',
        labelValue: 'sw-settings-product-feature-sets.valuesCard.labelValue',
        labelType: 'sw-settings-product-feature-sets.valuesCard.labelType',
        labelPosition: 'sw-settings-product-feature-sets.valuesCard.labelPosition',
        labelReferencePriceType: 'sw-settings-product-feature-sets.modal.label.referencePrice',
        labelReferencePriceValue: 'sw-settings-product-feature-sets.modal.textReferencePriceLabel'
    };

    const valuesCard = (additionalOptions = {}) => {
        return shallowMount(Shopware.Component.build('sw-settings-product-feature-sets-values-card'), {
            stubs: {
                'sw-card': Shopware.Component.build('sw-card'),
                'sw-container': true,
                'sw-simple-search-field': true,
                'sw-button': true,
                'sw-icon': true,
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-loader': true,
                'sw-checkbox-field': true,
                'sw-data-grid-column-position': true,
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
        wrapper = valuesCard();
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

    it('shows a list of features', () => {
        const root = findSecure(wrapper, `.${classes.componentRoot}`);
        const list = findSecure(root, `.${classes.valueList}`);
        const header = findSecure(list, `.${classes.valueListHeader}`);
        const body = findSecure(list, `.${classes.valueListBody}`);
        const firstRow = findSecure(body, `.${classes.valueListRow}`);

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
});
