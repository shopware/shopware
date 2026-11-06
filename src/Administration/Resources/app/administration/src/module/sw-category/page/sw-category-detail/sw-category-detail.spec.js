/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-category/page/sw-category-detail', () => {
    const saveMock = jest.fn(() => Promise.resolve());
    async function createWrapper({ props = {}, onGet = () => {} } = {}) {
        return mount(await wrapTestComponent('sw-category-detail', { sync: true }), {
            props,
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                    <div>
                        <slot name="smart-bar-actions"></slot>
                        <slot></slot>
                        <slot name="side-content"></slot>
                    </div>`,
                    },
                    'sw-category-tree': {
                        template: '<div class="sw-category-tree"></div>',
                        props: ['allowEdit', 'allowCreate', 'allowDelete'],
                    },
                    'sw-button-process': {
                        template: '<div class="sw-button-process"><slot></slot></div>',
                        props: ['disabled'],
                    },
                    'sw-sidebar-collapse': {
                        template: `
                    <div class="sw-sidebar-collapse">
                        <slot name="header"></slot>
                        <slot name="actions"></slot>
                        <slot name="content"></slot>
                    </div>`,
                    },
                    'sw-collapse': await wrapTestComponent('sw-collapse'),
                    'sw-landing-page-tree': true,
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'sw-skeleton': true,
                    'sw-category-view': true,
                    'sw-category-entry-point-overwrite-modal': true,
                    'sw-landing-page-view': true,
                    'sw-discard-changes-modal': true,
                    'sw-empty-state': true,
                },
                provide: {
                    customFieldDataProviderService: { getCustomFieldSets: () => Promise.resolve([]) },
                    cmsService: {
                        getEntityMappingTypes: () => {},
                    },
                    repositoryFactory: {
                        create: () => ({
                            search: () =>
                                Promise.resolve({
                                    get: () => ({ sections: [] }),
                                }),
                            save: saveMock,
                            get: (id, context, criteria) => {
                                onGet(criteria);

                                return Promise.resolve({
                                    slotConfig: '',
                                    navigationSalesChannels: [],
                                    footerSalesChannels: [],
                                    serviceSalesChannels: [],
                                });
                            },
                        }),
                    },
                    seoUrlService: {},
                    systemConfigApiService: {
                        getValues: () =>
                            Promise.resolve({
                                'core.cms.default_category_cms_page': 'foo',
                            }),
                    },
                },
            },
        });
    }

    beforeEach(() => {
        global.activeAclRoles = [];

        Shopware.Store.unregister('cmsPage');
        Shopware.Store.register({
            id: 'cmsPage',
            state: () => ({
                currentPage: null,
            }),
            actions: {
                resetCmsPageState: () => {},
                setCurrentMappingEntity: () => {},
                setCurrentMappingTypes: () => {},
                setCurrentDemoEntity: () => {},
                setCurrentPage: () => {},
            },
        });
    });

    it('should not allow to modify', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swCategoryDetail').category = {
            slotConfig: '',
        };

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(true);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(false);
        expect(categoryTree.props('allowEdit')).toBe(false);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to edit', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        Shopware.Store.get('swCategoryDetail').category = {
            slotConfig: '',
        };

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(false);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to create', async () => {
        global.activeAclRoles = ['category.creator', 'category.editor'];

        const wrapper = await createWrapper();

        Shopware.Store.get('swCategoryDetail').category = {
            slotConfig: '',
        };

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(true);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to delete', async () => {
        global.activeAclRoles = ['category.creator', 'category.editor', 'category.deleter'];

        const wrapper = await createWrapper();

        Shopware.Store.get('swCategoryDetail').category = {
            slotConfig: '',
        };

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(true);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(true);
    });

    it('should set default layout', async () => {
        global.activeAclRoles = ['category.creator', 'category.editor', 'category.deleter'];

        const wrapper = await createWrapper();

        Shopware.Store.get('swCategoryDetail').category = {
            slotConfig: '',
            cmsPageId: 'foo',
            navigationSalesChannels: [],
            footerSalesChannels: [],
            serviceSalesChannels: [],
        };

        await wrapper.setData({
            isLoading: false,
            cmsPage: null,
        });

        await wrapper.setProps({
            categoryId: 'foo',
        });

        await wrapper.vm.onSave();

        const lastCallParameters = saveMock.mock.lastCall;
        expect(lastCallParameters[0].cmsPageId).toBeUndefined();
    });

    it('should save changed slot config fields only', async () => {
        const wrapper = await createWrapper();

        const origin = {
            sections: [
                {
                    id: 'section1',
                    blocks: [
                        {
                            id: 'block1',
                            slots: [
                                {
                                    id: 'slot1',
                                    translated: { config: { key1: { value: 'value1' }, key2: { value: 'value2' } } },
                                },
                                { id: 'slot2', translated: { config: { key1: { value: 'value1' } } } },
                            ],
                        },
                    ],
                },
            ],
        };

        const changes = {
            sections: [
                {
                    id: 'section1',
                    blocks: [
                        {
                            id: 'block1',
                            slots: [
                                { id: 'slot1', config: { key1: { value: 'newValue1' }, key2: { value: 'value2' } } },
                                { id: 'slot2', config: { key1: { value: 'newValue1' } } },
                            ],
                        },
                    ],
                },
            ],
        };

        const expectedSlotOverrides = {
            slot1: { key1: { value: 'newValue1' } },
            slot2: { key1: { value: 'newValue1' } },
        };

        const result = wrapper.vm.extractSlotOverrides(origin, changes);

        expect(result).toEqual(expectedSlotOverrides);
    });

    // @deprecated tag:v6.8.0 - The test will be removed when the entry point sales channels are always limited.
    it.deprecated('v6.8.0.0')('loads all sales channel fields of the entry point sales channels before 6.8', async () => {
        const categoryCriteria = [];
        await createWrapper({
            props: { categoryId: 'category-id' },
            onGet: (criteria) => categoryCriteria.push(criteria.parse()),
        });
        await flushPromises();

        expect(categoryCriteria.length).toBeGreaterThan(0);
        expect(categoryCriteria[0].associations).toHaveProperty('navigationSalesChannels');
        expect(categoryCriteria[0].includes).toBeUndefined();
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'loads only the sales channel fields the entry point card and its modals use',
        async () => {
            const categoryCriteria = [];
            await createWrapper({
                props: { categoryId: 'category-id' },
                onGet: (criteria) => categoryCriteria.push(criteria.parse()),
            });
            await flushPromises();

            expect(categoryCriteria.length).toBeGreaterThan(0);
            const criteria = categoryCriteria[0];
            expect(criteria.associations.navigationSalesChannels.associations.homeCmsPage.associations).toHaveProperty(
                'previewMedia',
            );
            expect(criteria.associations).toHaveProperty('footerSalesChannels');
            expect(criteria.associations).toHaveProperty('serviceSalesChannels');
            expect(criteria.includes).toEqual({
                sales_channel: [
                    'id',
                    'name',
                    'translated',
                    'navigationCategoryId',
                    'footerCategoryId',
                    'serviceCategoryId',
                    'homeEnabled',
                    'homeName',
                    'homeMetaTitle',
                    'homeMetaDescription',
                    'homeKeywords',
                    'homeCmsPageId',
                    'homeCmsPage',
                ],
            });
        },
    );

    it('should load landing page translations for CMS inheritance handling', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.landingPageCriteria.associations.some(({ association }) => association === 'translations')).toBe(
            true,
        );
    });
});
