import { shallowMount, Wrapper } from '@vue/test-utils';

import 'src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-radio-field';

describe('src/module/sw-settings-product-feature-sets/component/sw-settings-product-feature-sets-modal', () => {
    let wrapper;

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
        productInformationListCellContent: 'sw-data-grid__cell-content'
    };

    const text = {
        propertyListNameHeader: 'sw-settings-product-feature-sets.modal.textPropertyLabel',
        customFieldListNameHeader: 'sw-settings-product-feature-sets.modal.labelName',
        customFieldListTypeHeader: 'sw-settings-product-feature-sets.valuesCard.labelType',
        productInformationListNameHeader: 'sw-settings-product-feature-sets.modal.labelName'
    };

    function getPageConfig(config) {
        return {
            data() {
                return {
                    showPageOne: false,
                    showCustomField: false,
                    showPropertyGroups: false,
                    showProductInfo: false,
                    ...config
                };
            }
        };
    }

    const modal = (additionalOptions = {}) => {
        return shallowMount(Shopware.Component.build('sw-settings-product-feature-sets-modal'), {
            stubs: {
                'sw-modal': true,
                'sw-radio-field': Shopware.Component.build('sw-radio-field'),
                'sw-base-field': true,
                'sw-button': true,
                'sw-icon': true,
                'sw-simple-search-field': Shopware.Component.build('sw-simple-search-field'),
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-field': true,
                'sw-data-grid-skeleton': true,
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
            data() {
                return {
                    showPageOne: true,
                    showCustomField: false,
                    showPropertyGroups: false,
                    showProductInfo: false
                };
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
                shortcutService: {
                    startEventListener: () => {
                    },
                    stopEventListener: () => {
                    }
                },
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

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be able to instantiate', () => {
        wrapper = modal();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('has the correct class', () => {
        wrapper = modal();

        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it('contains the options container', () => {
        wrapper = modal(getPageConfig({ showPageOne: true }));

        const root = findSecure(wrapper, `.${classes.componentRoot}`);
        const optionsContainer = findSecure(root, `.${classes.optionsContainer}`);

        expect(optionsContainer.exists()).toBeTruthy();
        expect(optionsContainer.props().options).toHaveLength(4);

        // Check wether all possible feature types are shown
        ['property', 'customField', 'product', 'referencePrice'].forEach((type) => {
            expect(optionsContainer.props().options
                .filter(option => option.value === type))
                .toHaveLength(1);
        });
    });

    it('contains the custom field list', () => {
        wrapper = modal(getPageConfig({ showCustomField: true }));

        const root = findSecure(wrapper, `.${classes.componentRoot}`);

        [
            classes.customFieldListToolbar,
            classes.customFieldListSearchField,
            classes.customFieldListHeader,
            classes.customFieldList
        ].forEach((className) => {
            expect(findSecure(root, `.${className}`).exists()).toBeTruthy();
        });

        const customFieldListHeader = findSecure(root, `.${classes.customFieldListHeader}`);
        const customFieldListHeaderContent = customFieldListHeader.findAll(`.${classes.customFieldListCellContent}`);

        expect(customFieldListHeaderContent.at(1).text()).toEqual(text.customFieldListNameHeader);
        expect(customFieldListHeaderContent.at(2).text()).toEqual(text.customFieldListTypeHeader);
    });

    it('contains the property group list', () => {
        wrapper = modal(getPageConfig({ showPropertyGroups: true }));

        const root = findSecure(wrapper, `.${classes.componentRoot}`);

        [
            classes.propertyListToolbar,
            classes.propertyListSearchField,
            classes.propertyListHeader,
            classes.propertyList
        ].forEach((className) => {
            expect(findSecure(root, `.${className}`).exists()).toBeTruthy();
        });

        const propertyListHeader = findSecure(root, `.${classes.propertyListHeader}`);
        const propertyListHeaderContent = propertyListHeader.findAll(`.${classes.propertyListCellContent}`);

        expect(propertyListHeaderContent.at(1).text()).toEqual(text.propertyListNameHeader);
    });

    it('contains the product information list', () => {
        wrapper = modal(getPageConfig({ showCustomField: true }));

        const root = findSecure(wrapper, `.${classes.componentRoot}`);

        [
            classes.productInformationListHeader,
            classes.productInformationList,
            classes.productInformationListCellContent
        ].forEach((className) => {
            expect(findSecure(root, `.${className}`).exists()).toBeTruthy();
        });

        const propertyListHeader = findSecure(root, `.${classes.propertyListHeader}`);
        const propertyListHeaderContent = propertyListHeader.findAll(`.${classes.propertyListCellContent}`);

        expect(propertyListHeaderContent.at(1).text()).toEqual(text.productInformationListNameHeader);
    });
});
