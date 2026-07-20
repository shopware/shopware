/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

const deviceMock = {
    onResize: jest.fn(),
    removeResizeListener: jest.fn(),
};

async function createWrapper(privileges = [], customStubs = {}) {
    return mount(
        await wrapTestComponent('sw-settings-language-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $device: deviceMock,
                    $route: {
                        params: {
                            sortBy: 'sortBy',
                        },
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                    $router: {
                        push: jest.fn(),
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([
                                    {
                                        name: 'English',
                                    },
                                    {
                                        name: 'German',
                                    },
                                    {
                                        name: 'Vietnamese',
                                    },
                                ]);
                            },
                        }),
                    },
                    translationService: {
                        getList: jest.fn().mockResolvedValue({ total: 0, items: [] }),
                        update: jest.fn().mockResolvedValue(),
                        install: jest.fn().mockResolvedValue(),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },

                    detailPageLinkText(allowEdit) {
                        return allowEdit ? this.$t('global.default.edit') : this.$t('global.default.view');
                    },

                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },

                    setSwPageSidebarOffset: () => {},
                    removeSwPageSidebarOffset: () => {},
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },

                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'sw-sidebar': await wrapTestComponent('sw-sidebar', { sync: true }),
                    'sw-sidebar-item': await wrapTestComponent('sw-sidebar-item', { sync: true }),
                    'sw-sidebar-navigation-item': await wrapTestComponent('sw-sidebar-navigation-item', { sync: true }),
                    'mt-tooltip': true,
                    'sw-collapse': true,
                    'sw-context-menu-item': true,
                    'sw-entity-listing': {
                        inject: ['detailPageLinkText'],
                        props: [
                            'items',
                            'dataSource',
                            'allowEdit',
                            'allowView',
                            'detailRoute',
                        ],
                        template: `
                    <div>
                        <template v-for="item in (dataSource || items)">
                            <slot name="detail-action" v-bind="{ item }">
                                <sw-context-menu-item
                                    v-if="detailRoute"
                                    :disabled="!allowEdit && !allowView || undefined"
                                    class="sw-entity-listing__context-menu-edit-action">
                                    {{ detailPageLinkText(allowEdit) }}
                                </sw-context-menu-item>
                            </slot>
                            <slot name="delete-action" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `,
                    },
                    'sw-text-field': true,
                    'router-link': true,
                    'sw-card-view': true,
                    'sw-card': true,
                    'sw-label': true,
                    'sw-settings-language-add-modal': true,
                    ...customStubs,
                },
            },
        },
    );
}

describe('module/sw-settings-language/page/sw-settings-language-list', () => {
    it('should register the open filters shortcut', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$options.shortcuts.OF).toBe('openFilterSidebar');
    });

    it('should open the filter sidebar via the open-filters shortcut', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-sidebar').classes()).not.toContain('is--opened');

        wrapper.vm.openFilterSidebar();
        await flushPromises();

        expect(wrapper.find('.sw-sidebar').classes()).toContain('is--opened');
    });

    it('should be able to create a new language', async () => {
        const wrapper = await createWrapper([
            'language.creator',
        ]);
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-language-list__button-create');

        expect(addButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-language-list__button-create');

        expect(addButton.attributes('disabled')).toBeDefined();
    });

    it('should be able to view a language', async () => {
        const wrapper = await createWrapper([
            'language.viewer',
        ]);
        await flushPromises();

        const elementItemAction = wrapper.find('.sw-entity-listing__context-menu-edit-action');

        expect(elementItemAction.attributes().disabled).toBeFalsy();
        expect(elementItemAction.text()).toBe('global.default.view');
    });

    it('should be able to edit a language', async () => {
        const wrapper = await createWrapper([
            'language.editor',
        ]);
        await flushPromises();

        const elementItemAction = wrapper.find('.sw-entity-listing__context-menu-edit-action');

        expect(elementItemAction.attributes().disabled).toBeFalsy();
        expect(elementItemAction.text()).toBe('global.default.edit');
    });

    it('should not be able to edit a language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const elementItemAction = wrapper.find('.sw-entity-listing__context-menu-edit-action');

        expect(elementItemAction.attributes().disabled).toBeTruthy();
        expect(elementItemAction.text()).toBe('global.default.view');
    });

    it('should be able to delete a language', async () => {
        const wrapper = await createWrapper([
            'language.deleter',
        ]);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-language-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-language-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to inline edit a language', async () => {
        const wrapper = await createWrapper([
            'language.editor',
        ]);
        await flushPromises();

        const entityListing = wrapper.find('.sw-settings-language-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to inline edit a language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const entityListing = wrapper.find('.sw-settings-language-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should contain a listing criteria with correct properties', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.listingCriteria).toEqual(
            expect.objectContaining({
                associations: expect.arrayContaining([
                    expect.objectContaining({
                        association: 'translationCode',
                    }),
                ]),
            }),
        );
    });

    it('should show a link to the snippets page', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const snippetLink = wrapper.find('.sw-settings-language-list__snippet-link');
        expect(snippetLink.exists()).toBe(true);
        expect(snippetLink.text()).toContain('manageSnippets');
    });

    it('should load the salesChannels association', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.listingCriteria).toEqual(
            expect.objectContaining({
                associations: expect.arrayContaining([
                    expect.objectContaining({
                        association: 'salesChannels',
                    }),
                ]),
            }),
        );
    });

    it('should render the sales channels and snippet status columns', async () => {
        const wrapper = await createWrapper();

        const columns = wrapper.vm.getColumns.map((column) => column.property);

        expect(columns).toContain('salesChannels');
        expect(columns).toContain('snippetStatus');
    });

    it('should render an update all snippets button', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const updateButton = wrapper.find('.sw-settings-language-list__button-update-snippets');

        expect(updateButton.exists()).toBe(true);
    });

    it('should disable the update-all button when nothing is updatable', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const updateButton = wrapper.find('.sw-settings-language-list__button-update-snippets');

        // no metadata => nothing updatable
        expect(updateButton.attributes('disabled')).toBeDefined();

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', updateAvailable: true } };
        await flushPromises();

        expect(updateButton.attributes('disabled')).toBeUndefined();
    });

    it('should label the assigned sales channels by count', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.salesChannelLabel({ salesChannels: [] })).toContain('salesChannelNone');
        expect(wrapper.vm.salesChannelLabel({})).toContain('salesChannelNone');
        expect(
            wrapper.vm.salesChannelLabel({
                salesChannels: [
                    {},
                    {},
                    {},
                ],
            }),
        ).toContain('salesChannelCount');
    });

    it('should only expose the update status derived from the translation metadata', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = {
            'es-ES': { locale: 'es-ES', lastUpdate: '2026-07-20T00:00:00+00:00', updateAvailable: false },
            'fr-FR': { locale: 'fr-FR', lastUpdate: '2026-07-20T00:00:00+00:00', updateAvailable: true },
        };

        // Shopware default languages are never checked for snippet updates
        expect(wrapper.vm.getSnippetStatus({ locale: { code: 'de-DE' } })).toBeNull();
        expect(wrapper.vm.getSnippetStatus({ locale: { code: 'en-GB' } })).toBeNull();
        // up-to-date and custom locales no longer render a badge
        expect(wrapper.vm.getSnippetStatus({ locale: { code: 'es-ES' } })).toBeNull();
        expect(wrapper.vm.getSnippetStatus({ locale: { code: 'zh-CN' } })).toBeNull();
        // only an available update is surfaced
        expect(wrapper.vm.getSnippetStatus({ locale: { code: 'fr-FR' } })).toBe('updateAvailable');
    });

    it('should derive isUpdatingSnippets from the currently updating locales', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isUpdatingSnippets).toBe(false);

        wrapper.vm.updatingLocales = ['fr-FR'];

        expect(wrapper.vm.isUpdatingSnippets).toBe(true);
    });

    it('should open the add language modal via the add button', async () => {
        const wrapper = await createWrapper(['language.creator']);
        await flushPromises();

        expect(wrapper.findComponent('sw-settings-language-add-modal-stub').exists()).toBe(false);

        await wrapper.find('.sw-settings-language-list__button-create').trigger('click');

        expect(wrapper.vm.showAddLanguageModal).toBe(true);
        expect(wrapper.findComponent('sw-settings-language-add-modal-stub').exists()).toBe(true);
    });

    it('should close the modal and redirect to the detail page of the added language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.showAddLanguageModal = true;
        jest.spyOn(wrapper.vm.languageRepository, 'search').mockResolvedValue({
            first: () => ({ id: 'new-language-id' }),
        });

        await wrapper.vm.onLanguageAdded('fr-FR');

        expect(wrapper.vm.showAddLanguageModal).toBe(false);
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.settings.language.detail',
            params: { id: 'new-language-id' },
            query: { languageCreated: 'true' },
        });
    });

    it('should reload the list when the added language cannot be resolved', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        jest.spyOn(wrapper.vm.languageRepository, 'search').mockResolvedValue({
            first: () => null,
        });
        const getListSpy = jest.spyOn(wrapper.vm, 'getList').mockResolvedValue();

        await wrapper.vm.onLanguageAdded('xx-XX');

        expect(getListSpy).toHaveBeenCalled();
        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
    });

    it('should sequentially install every updatable language when updating all snippets', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = {
            'fr-FR': { locale: 'fr-FR', updateAvailable: true },
            'es-ES': { locale: 'es-ES', updateAvailable: true },
            'it-IT': { locale: 'it-IT', updateAvailable: false },
        };

        await wrapper.vm.onUpdateAllSnippets();

        expect(wrapper.vm.translationService.install).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.translationService.install).toHaveBeenNthCalledWith(1, {
            locales: ['fr-FR'],
            activate: true,
        });
        expect(wrapper.vm.translationService.install).toHaveBeenNthCalledWith(2, {
            locales: ['es-ES'],
            activate: true,
        });
        expect(wrapper.vm.translationService.update).not.toHaveBeenCalled();
    });

    it('should load the translation metadata once on creation and not on every list fetch', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.translationService.getList).toHaveBeenCalledTimes(1);

        await wrapper.vm.getList();

        expect(wrapper.vm.translationService.getList).toHaveBeenCalledTimes(1);
    });

    it('should refetch the translation metadata on refresh', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.translationService.getList).toHaveBeenCalledTimes(1);

        wrapper.vm.onRefresh();
        await flushPromises();

        expect(wrapper.vm.translationService.getList).toHaveBeenCalledTimes(2);
    });

    it('should update the snippets of a single language and refetch the metadata', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onUpdateSnippets({ locale: { code: 'fr-FR' } });

        expect(wrapper.vm.translationService.install).toHaveBeenCalledWith({
            locales: ['fr-FR'],
            activate: true,
        });
        // created (1) + reload after the update (2)
        expect(wrapper.vm.translationService.getList).toHaveBeenCalledTimes(2);
    });

    it('should mark only the updating language as updating', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = {
            'fr-FR': { locale: 'fr-FR', updateAvailable: true },
            'es-ES': { locale: 'es-ES', updateAvailable: true },
        };
        wrapper.vm.updatingLocales = ['fr-FR'];

        expect(wrapper.vm.getSnippetStatus({ locale: { code: 'fr-FR' } })).toBe('updating');
        expect(wrapper.vm.getSnippetStatus({ locale: { code: 'es-ES' } })).toBe('updateAvailable');
    });

    it('should only offer selected languages that actually have an update available', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = {
            'fr-FR': { locale: 'fr-FR', updateAvailable: true },
            'es-ES': { locale: 'es-ES', updateAvailable: true },
            'it-IT': { locale: 'it-IT', updateAvailable: false },
        };
        wrapper.vm.snippetSelection = {
            a: { locale: { code: 'fr-FR' } },
            b: { locale: { code: 'es-ES' } },
            c: { locale: { code: 'it-IT' } },
            d: { locale: { code: 'de-DE' } },
        };

        expect(wrapper.vm.selectedUpdatableLocales).toEqual([
            'fr-FR',
            'es-ES',
        ]);
    });

    it('should sequentially install the snippets for each selected updatable language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = {
            'fr-FR': { locale: 'fr-FR', updateAvailable: true },
            'es-ES': { locale: 'es-ES', updateAvailable: true },
        };
        wrapper.vm.snippetSelection = {
            a: { locale: { code: 'fr-FR' } },
            b: { locale: { code: 'es-ES' } },
        };

        await wrapper.vm.onUpdateSelectedSnippets();

        expect(wrapper.vm.translationService.install).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.translationService.install).toHaveBeenNthCalledWith(1, {
            locales: ['fr-FR'],
            activate: true,
        });
        expect(wrapper.vm.translationService.install).toHaveBeenNthCalledWith(2, {
            locales: ['es-ES'],
            activate: true,
        });
    });
});
