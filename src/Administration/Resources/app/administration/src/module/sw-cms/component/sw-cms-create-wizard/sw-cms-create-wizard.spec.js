/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import swCmsCreateWizard from './index';
import swCmsStageSectionSelection from '../sw-cms-stage-section-selection';

const expectedVisiblePageTypes = {
    page: {
        name: 'page',
        icon: 'regular-lightbulb',
        title: 'sw-cms.detail.label.pageType.page',
        class: ['sw-cms-create-wizard__page-type-page'],
        hideInList: false,
    },
    landingpage: {
        name: 'landingpage',
        icon: 'regular-dashboard',
        title: 'sw-cms.detail.label.pageType.landingpage',
        class: ['sw-cms-create-wizard__page-type-landingpage'],
        hideInList: false,
    },
    product_list: {
        name: 'product_list',
        icon: 'regular-shopping-basket',
        title: 'sw-cms.detail.label.pageType.productList',
        class: ['sw-cms-create-wizard__page-type-product-list'],
        hideInList: false,
    },
    product_detail: {
        name: 'product_detail',
        icon: 'regular-tag',
        title: 'sw-cms.detail.label.pageType.productDetail',
        class: ['sw-cms-create-wizard__page-type-product-detail'],
        hideInList: false,
    },
    custom_entity_detail: {
        name: 'custom_entity_detail',
        icon: 'regular-tag',
        title: 'sw-cms.detail.label.pageType.customEntityDetail',
        class: ['sw-cms-create-wizard__page-type-custom-entity-detail'],
        hideInList: false,
    }
};

Shopware.Component.register('sw-cms-create-wizard', swCmsCreateWizard);
Shopware.Component.register('sw-cms-stage-section-selection', swCmsStageSectionSelection);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-create-wizard'), {
        stubs: {
            'router-link': true,
            'sw-icon': true,
            'sw-cms-stage-section-selection': await Shopware.Component.build('sw-cms-stage-section-selection'),
            'sw-single-select': true,
            'sw-text-field': true,
            'sw-button': true,
        },
        propsData: {
            page: {},
        },
        provide: {
            cmsPageTypeService: {
                getType: (name) => {
                    return expectedVisiblePageTypes[name];
                },
                getVisibleTypes: () => {
                    return Object.values(expectedVisiblePageTypes);
                },
            },
            customEntityDefinitionService: {
                getCmsAwareDefinitions: () => ['some-content-to-result-in-true']
            },
        },
    });
}

let wrapper;

describe('module/sw-cms/component/sw-cms-create-wizard', () => {
    beforeEach(async () => {
        wrapper = await createWrapper();

        if (Shopware.State.get('cmsPageState')) {
            Shopware.State.unregisterModule('cmsPageState');
        }

        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            mutations: {
                setCurrentPageType: () => {},
            }
        });
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the correct page types in selection step', async () => {
        const typeSelection = wrapper.findAll('.sw-cms-create-wizard__page-type');

        expect(typeSelection.length).toBe(5);
    });

    it('should show the correct pageType selection for type "page"', async () => {
        const typePage = wrapper.find('.sw-cms-create-wizard__page-type-page');
        await typePage.trigger('click');

        const noSidebarSection = wrapper.find('.sw-cms-stage-section-selection__default');
        await noSidebarSection.trigger('click');

        const nameField = wrapper.find('.sw-cms-create-wizard__page-completion-name');
        expect(nameField.exists()).toBe(true);

        const customEntitySelection = wrapper.find('.sw-cms-create-wizard__page-completion-custom-entity');
        expect(customEntitySelection.exists()).toBe(false);
    });

    it('should show the correct pageType selection for type "custom_entity_detail"', async () => {
        const typePage = wrapper.find('.sw-cms-create-wizard__page-type-custom-entity-detail');
        await typePage.trigger('click');

        const noSidebarSection = wrapper.find('.sw-cms-stage-section-selection__default');
        await noSidebarSection.trigger('click');

        const nameField = wrapper.find('.sw-cms-create-wizard__page-completion-name');
        expect(nameField.exists()).toBe(true);

        const customEntitySelection = wrapper.find('.sw-cms-create-wizard__page-completion-custom-entity');
        expect(customEntitySelection.exists()).toBe(true);
    });
});
