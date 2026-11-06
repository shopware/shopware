/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-settings-seo/component/sw-seo-url/store';

describe('src/module/sw-category/page/sw-category-detail', () => {
    const saveMock = jest.fn(() => Promise.resolve());
    const updateCanonicalUrlMock = jest.fn(() => Promise.resolve());
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-category-detail', { sync: true }), {
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
                            get: () =>
                                Promise.resolve({
                                    slotConfig: '',
                                    navigationSalesChannels: [],
                                    footerSalesChannels: [],
                                    serviceSalesChannels: [],
                                }),
                        }),
                    },
                    seoUrlService: { updateCanonicalUrl: updateCanonicalUrlMock },
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

    it('should load landing page translations for CMS inheritance handling', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.landingPageCriteria.associations.some(({ association }) => association === 'translations')).toBe(
            true,
        );
    });

    describe('saving SEO URLs', () => {
        async function saveWithSeoUrl({ foreignKey = 'category-id', seoPathInfo, isModified }) {
            global.activeAclRoles = ['category.editor'];
            const wrapper = await createWrapper();

            Shopware.Store.get('swCategoryDetail').category = {
                id: 'category-id',
                slotConfig: '',
                navigationSalesChannels: [],
                footerSalesChannels: [],
                serviceSalesChannels: [],
            };
            await wrapper.setData({ isLoading: false });

            const original = {
                id: 'sc',
                foreignKey,
                salesChannelId: 'sc',
                languageId: 'lang',
                seoPathInfo: 'Custom/Path',
                isModified,
            };
            const seoStore = Shopware.Store.get('swSeoUrl');
            seoStore.originalSeoUrls = [original];
            seoStore.seoUrlCollection = [{ ...original, seoPathInfo }];

            await wrapper.get('.sw-category-detail__save-action').trigger('click');
            await flushPromises();
        }

        beforeEach(() => {
            updateCanonicalUrlMock.mockClear();
            saveMock.mockClear();
        });

        afterEach(() => {
            const seoStore = Shopware.Store.get('swSeoUrl');
            seoStore.originalSeoUrls = [];
            seoStore.seoUrlCollection = null;
        });

        it.each([
            { state: 'cleared', seoPathInfo: '', originalIsModified: true, isModified: false },
            { state: 'whitespace-only', seoPathInfo: '  ', originalIsModified: true, isModified: false },
            { state: 'edited', seoPathInfo: 'New/Path', originalIsModified: false, isModified: true },
        ])(
            'should send a $state SEO URL with isModified=$isModified',
            async ({ seoPathInfo, originalIsModified, isModified }) => {
                await saveWithSeoUrl({ seoPathInfo, isModified: originalIsModified });

                expect(updateCanonicalUrlMock).toHaveBeenCalledTimes(1);
                expect(updateCanonicalUrlMock).toHaveBeenCalledWith(
                    expect.objectContaining({ id: 'sc', seoPathInfo, isModified }),
                    'lang',
                );
            },
        );

        it('should send SEO URLs after the category is saved', async () => {
            await saveWithSeoUrl({ seoPathInfo: '', isModified: true });

            expect(saveMock.mock.invocationCallOrder[0]).toBeLessThan(updateCanonicalUrlMock.mock.invocationCallOrder[0]);
        });

        it('should not send SEO URLs left over from another category', async () => {
            await saveWithSeoUrl({ foreignKey: 'other-category-id', seoPathInfo: '', isModified: true });

            expect(updateCanonicalUrlMock).not.toHaveBeenCalled();
        });
    });
});
