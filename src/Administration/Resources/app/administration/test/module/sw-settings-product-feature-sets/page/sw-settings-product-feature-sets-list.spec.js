import { createLocalVue, shallowMount, Wrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data-new/entity-collection.data';

import 'src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-list';
import 'src/app/component/structure/sw-page';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';

const { Mixin } = Shopware;

describe('src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-list', () => {
    let wrapper;

    const classes = {
        componentRoot: 'sw-settings-product-feature-sets-list',
        featureSetList: 'sw-settings-product-feature-sets-list-grid',
        featureSetListHeader: 'sw-data-grid__header',
        featureSetListBody: 'sw-data-grid__body',
        featureSetListRow: 'sw-data-grid__row ',
        featureSetListCellContent: 'sw-data-grid__cell-content'
    };

    const text = {
        columnLabelTemplate: 'sw-settings-product-feature-sets.list.columnTemplate',
        columnLabelDescription: 'sw-settings-product-feature-sets.list.columnDescription',
        columnLabelValues: 'sw-settings-product-feature-sets.list.columnValues',
        featureSetDetailRouterLink: 'sw.settings.product.feature.sets.detail',
        referencePriceLabel: 'sw-settings-product-feature-sets.modal.label.referencePrice',
        featureSetName: '2c1c9361-88e2-48ab-b14d-973d080717af',
        featureSetDescription: '71aa7417-717a-4f8d-ad37-7cff58f81f58'
    };

    const listPage = (additionalOptions = {}) => {
        const localVue = createLocalVue();

        localVue.directive('tooltip', {});

        return shallowMount(Shopware.Component.build('sw-settings-product-feature-sets-list'), {
            localVue,
            stubs: {
                'sw-page': Shopware.Component.build('sw-page'),
                'sw-notification-center': true,
                'sw-language-switch': true,
                'sw-search-bar': true,
                'sw-icon': true,
                'sw-button': true,
                'sw-entity-listing': Shopware.Component.build('sw-entity-listing'),
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-checkbox-field': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-data-grid-settings': true,
                'sw-pagination': true,
                'router-link': true,
                i18n: true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $te: (translationPath) => translationPath,
                $device: {
                    onResize: () => {},
                    getSystemKey: () => {}
                },
                $route: {
                    meta: {
                        $module: {}
                    },
                    query: {}
                },
                $router: {
                    replace: () => {}
                }
            },
            data() {
                return {
                    productFeatureSets: new EntityCollection(
                        null,
                        'product_feature_set',
                        Shopware.Context.api,
                        {
                            page: {}
                        },
                        [
                            {
                                id: 'ecf55d8cbcf5496d8e42aa146ec4ba95',
                                name: text.featureSetName,
                                description: text.featureSetDescription,
                                features: [
                                    {
                                        type: 'referencePrice',
                                        id: null,
                                        name: null,
                                        position: 0
                                    }
                                ]
                            }
                        ]
                    )
                };
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve()
                    })
                },
                validationService: {},
                mixins: [
                    Mixin.getByName('listing')
                ]
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
        wrapper = listPage();
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

    it('should show a list of featuresets', () => {
        const root = findSecure(wrapper, `.${classes.componentRoot}`);
        const list = findSecure(root, `.${classes.featureSetList}`);
        const listBody = findSecure(root, `.${classes.featureSetListBody}`);
        const firstRow = findSecure(listBody, `.${classes.featureSetListRow}`);

        // Assert that all column labels are correct
        expect(list.props().columns.map(column => column.label)).toEqual([
            text.columnLabelTemplate,
            text.columnLabelDescription,
            text.columnLabelValues
        ]);

        // Assert that the column types are correct
        expect(list.props().columns.map(column => column.property)).toEqual([
            'name',
            'description',
            'features'
        ]);

        // Assert that the template's name links to the detail page
        expect(list.props().columns.shift().routerLink).toEqual(text.featureSetDetailRouterLink);

        const firstRowContent = firstRow.findAll(`.${classes.featureSetListCellContent}`).wrappers
            .slice(0, 4)
            .map(cell => cell.text())
            .filter(val => val !== '');

        // Assert that the template is rendered correctly
        expect(firstRowContent).toEqual([
            text.featureSetName,
            text.featureSetDescription,
            text.referencePriceLabel
        ]);
    });
});
