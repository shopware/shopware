/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

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
    },
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-create-wizard', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'router-link': true,
                    'sw-icon': true,
                    'sw-cms-stage-section-selection': await wrapTestComponent('sw-cms-stage-section-selection'),
                    'sw-single-select': true,
                    'sw-text-field': true,
                    'sw-button': true,
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
                        getCmsAwareDefinitions: () => [
                            'some-content-to-result-in-true',
                        ],
                    },
                },
            },
            props: {
                page: {
                    type: 'landingpage',
                    sections: [
                        {
                            id: 'section-1',
                            type: 'default',
                            blocks: [
                                {
                                    id: 'block-1',
                                    slots: [
                                        {
                                            id: 'slot-1',
                                            type: 'text',
                                            config: {
                                                content: {
                                                    source: 'static',
                                                    value: 'Lorem ipsum dolor sit amet',
                                                },
                                            },
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-create-wizard', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the correct page types in selection step', async () => {
        const wrapper = await createWrapper();
        const typeSelection = wrapper.findAll('.sw-cms-create-wizard__page-type');

        expect(typeSelection).toHaveLength(5);
    });

    it('should display the correct step name', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.getStepName(1)).toBe('pageType');
        expect(wrapper.vm.getStepName(2)).toBe('sectionType');
        expect(wrapper.vm.getStepName(3)).toBe('pageName');
        expect(wrapper.vm.getStepName(100)).toBe('');
    });

    const pageTypeDataProvider = [
        [
            'page',
            false,
        ],
        [
            'custom-entity-detail',
            true,
        ],
    ];
    it.each(pageTypeDataProvider)(
        'should show the correct pageType selection for type "%s"',
        async (pageType, expectedHasCustomEntitySelection) => {
            const wrapper = await createWrapper();
            const typePage = wrapper.find(`.sw-cms-create-wizard__page-type-${pageType}`);
            await typePage.trigger('click');
            await flushPromises();

            const noSidebarSection = wrapper.find('.sw-cms-stage-section-selection__default');
            await noSidebarSection.trigger('click');

            const nameField = wrapper.find('.sw-cms-create-wizard__page-completion-name');
            expect(nameField.exists()).toBe(true);

            const customEntitySelection = wrapper.find('.sw-cms-create-wizard__page-completion-custom-entity');
            expect(customEntitySelection.exists()).toBe(expectedHasCustomEntitySelection);
        },
    );

    it('should generate the correct pagePreviewMedia tag', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.pagePreviewMedia).toBe('url(administration/static/img/cms/preview_landingpage_default.png)');
    });

    it('should not generate any pagePreviewMedia, when no sections are set', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            page: {
                type: 'landingpage',
                sections: [],
            },
        });

        expect(wrapper.vm.pagePreviewMedia).toBe('');
    });

    it('should emit a wizard-complete event on page creation completion', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            page: {
                type: 'landingpage',
                name: 'nice name',
                sections: [],
            },
        });

        wrapper.vm.onCompletePageCreation();

        expect(wrapper.emitted('wizard-complete')).toBeTruthy();
    });

    it('should not emit anything on page creation completion, when no name has been set', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onCompletePageCreation();

        expect(wrapper.emitted('wizard-complete')).toBeFalsy();
    });

    it('should show the correct pageType selection for type "custom_entity_detail"', async () => {
        const wrapper = await createWrapper();
        const typePage = wrapper.find('.sw-cms-create-wizard__page-type-custom-entity-detail');
        await typePage.trigger('click');
        await flushPromises();

        const noSidebarSection = wrapper.find('.sw-cms-stage-section-selection__default');
        await noSidebarSection.trigger('click');

        const nameField = wrapper.find('.sw-cms-create-wizard__page-completion-name');
        expect(nameField.exists()).toBe(true);

        const customEntitySelection = wrapper.find('.sw-cms-create-wizard__page-completion-custom-entity');
        expect(customEntitySelection.exists()).toBe(true);
    });
});
