/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

const deviceMock = {
    onResize: jest.fn(),
    removeResizeListener: jest.fn(),
};

async function createWrapper(privileges = [], customStubs = {}) {
    const languageRepositoryMock = {
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
        syncDeleted: jest.fn().mockResolvedValue(),
    };

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
                        create: () => languageRepositoryMock,
                    },
                    translationService: {
                        getList: jest.fn().mockResolvedValue({ total: 0, items: [] }),
                        getMeta: jest.fn().mockResolvedValue({
                            builtInLocales: [
                                'de-DE',
                                'en-GB',
                            ],
                        }),
                        update: jest.fn().mockResolvedValue(),
                        install: jest.fn().mockResolvedValue(),
                        deleteTranslation: jest.fn().mockResolvedValue(),
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
                        methods: {
                            resetSelection: jest.fn(),
                        },
                        props: [
                            'items',
                            'dataSource',
                            'allowEdit',
                            'allowView',
                            'detailRoute',
                            'identifier',
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
                        <slot name="bulk-additional"></slot>
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

    it('should persist grid settings via a stable identifier', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const grid = wrapper.findComponent('.sw-settings-language-list-grid');

        expect(grid.props('identifier')).toBe('sw-settings-language-list');
    });

    it('should provide a parent column that is hidden by default', async () => {
        const wrapper = await createWrapper();

        const parentColumn = wrapper.vm.getColumns.find((column) => column.property === 'parent');

        expect(parentColumn).toBeDefined();
        expect(parentColumn.visible).toBe(false);
    });

    it('should only display a parent name for inherited languages', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.parentLanguages = {
            get: () => ({ name: 'English' }),
        };

        expect(wrapper.vm.getParentName({ parentId: null })).toBe('');
        expect(wrapper.vm.getParentName({ parentId: 'parent-id' })).toBe('English');
    });

    it('should render the update all snippets button when a language is updatable', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', updateAvailable: true } };
        await flushPromises();

        expect(wrapper.find('.sw-settings-language-list__button-update-snippets').exists()).toBe(true);
    });

    it('should hide the update-all button when nothing is updatable', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // no metadata => nothing updatable => button hidden
        expect(wrapper.find('.sw-settings-language-list__button-update-snippets').exists()).toBe(false);

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', updateAvailable: true } };
        await flushPromises();

        expect(wrapper.find('.sw-settings-language-list__button-update-snippets').exists()).toBe(true);
    });

    it('should disable the update-all button without the language editor privilege', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', updateAvailable: true } };
        await flushPromises();

        expect(wrapper.find('.sw-settings-language-list__button-update-snippets').attributes('disabled')).toBeDefined();
    });

    it('should enable the update-all button with the language editor privilege', async () => {
        const wrapper = await createWrapper(['language.editor']);
        await flushPromises();

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', updateAvailable: true } };
        await flushPromises();

        expect(wrapper.find('.sw-settings-language-list__button-update-snippets').attributes().disabled).toBeFalsy();
    });

    it('should only offer the bulk snippet update with the language editor privilege', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', updateAvailable: true } };
        wrapper.vm.snippetSelection = {
            a: { locale: { code: 'fr-FR' } },
        };
        await flushPromises();

        expect(wrapper.find('.sw-settings-language-list__bulk-update-snippets').exists()).toBe(false);
    });

    it('should offer the bulk snippet update to language editors', async () => {
        const wrapper = await createWrapper(['language.editor']);
        await flushPromises();

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', updateAvailable: true } };
        wrapper.vm.snippetSelection = {
            a: { locale: { code: 'fr-FR' } },
        };
        await flushPromises();

        expect(wrapper.find('.sw-settings-language-list__bulk-update-snippets').exists()).toBe(true);
    });

    it('should label the assigned sales channels by count', async () => {
        const wrapper = await createWrapper();

        // languages without assigned sales channels render nothing to keep the overview clean
        expect(wrapper.vm.salesChannelLabel({ salesChannels: [] })).toBe('');
        expect(wrapper.vm.salesChannelLabel({})).toBe('');
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

    it('should label the locale with its native name and the UI language', async () => {
        const wrapper = await createWrapper();
        const localeNameSpy = jest.spyOn(Shopware.Utils.format, 'localeName').mockReturnValue('Français (French, France)');

        expect(wrapper.vm.localeLabel({ locale: { code: 'fr-FR' } })).toBe('Français (French, France)');
        expect(localeNameSpy).toHaveBeenCalledWith('fr-FR');

        // languages without a loaded locale association render nothing instead of a broken label
        expect(wrapper.vm.localeLabel({})).toBe('');

        localeNameSpy.mockRestore();
    });

    it('should label pseudo languages with their own name instead of the borrowed locale', async () => {
        const wrapper = await createWrapper();
        const localeNameSpy = jest.spyOn(Shopware.Utils.format, 'localeName');

        wrapper.vm.translationMetadata = {
            'ach-UG': { locale: 'ach-UG', name: 'Acholi', isPseudoLanguage: true },
        };

        expect(wrapper.vm.localeLabel({ locale: { code: 'ach-UG' } })).toBe('Acholi');
        expect(localeNameSpy).not.toHaveBeenCalled();

        localeNameSpy.mockRestore();
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

        // update all re-fetches the current state first, so a stale in-memory list can never re-create a deleted language
        wrapper.vm.translationService.getList.mockResolvedValueOnce({
            items: [
                { locale: 'fr-FR', updateAvailable: true },
                { locale: 'es-ES', updateAvailable: true },
                { locale: 'it-IT', updateAvailable: false },
            ],
        });

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

    it('does not re-install a language that was removed since the list was last loaded', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // stale in-memory state still lists de-DE as updatable
        wrapper.vm.translationMetadata = {
            'de-DE': { locale: 'de-DE', updateAvailable: true },
        };

        // the current server state no longer reports de-DE (the language was deleted in the meantime)
        wrapper.vm.translationService.getList.mockResolvedValueOnce({ items: [] });

        await wrapper.vm.onUpdateAllSnippets();

        expect(wrapper.vm.translationService.install).not.toHaveBeenCalled();
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

        // the grid selection is cleared once the bulk update finishes
        expect(wrapper.vm.snippetSelection).toEqual({});
        expect(wrapper.vm.selectedUpdatableLocales).toEqual([]);
    });

    it('lists the single language and deletes it together with its files when the option is checked', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        jest.spyOn(wrapper.vm, 'invalidateLanguageCaches').mockImplementation(() => {});

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', lastUpdate: '2026-01-01T00:00:00+00:00' } };

        wrapper.vm.openDeleteModal([{ id: 'id-fr', name: 'Français', locale: { code: 'fr-FR' } }]);

        // the modal opens with the single language listed and the file option defaults to checked
        expect(wrapper.vm.showDeleteModal).toBe(true);
        expect(wrapper.vm.deleteCandidates).toHaveLength(1);
        expect(wrapper.vm.deleteTranslationFiles).toBe(true);
        expect(wrapper.vm.deleteCandidateInstalledLocales).toEqual(['fr-FR']);

        await wrapper.vm.confirmDelete();

        expect(wrapper.vm.languageRepository.syncDeleted).toHaveBeenCalledWith(['id-fr'], expect.anything());
        expect(wrapper.vm.translationService.deleteTranslation).toHaveBeenCalledWith('fr-FR');
        expect(wrapper.vm.showDeleteModal).toBe(false);
    });

    it('keeps the files when the delete option is left unchecked', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        jest.spyOn(wrapper.vm, 'invalidateLanguageCaches').mockImplementation(() => {});

        wrapper.vm.translationMetadata = { 'fr-FR': { locale: 'fr-FR', lastUpdate: '2026-01-01T00:00:00+00:00' } };

        wrapper.vm.openDeleteModal([{ id: 'id-fr', name: 'Français', locale: { code: 'fr-FR' } }]);
        wrapper.vm.deleteTranslationFiles = false;
        await wrapper.vm.confirmDelete();

        expect(wrapper.vm.languageRepository.syncDeleted).toHaveBeenCalledWith(['id-fr'], expect.anything());
        expect(wrapper.vm.translationService.deleteTranslation).not.toHaveBeenCalled();
    });

    it('offers no file option for a language without a downloaded translation', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.translationMetadata = {};
        wrapper.vm.openDeleteModal([{ id: 'id-fr', name: 'Français', locale: { code: 'fr-FR' } }]);

        expect(wrapper.vm.deleteCandidateInstalledLocales).toEqual([]);
    });

    it('lists all selected languages for a bulk delete and only removes files of installed ones', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        jest.spyOn(wrapper.vm, 'invalidateLanguageCaches').mockImplementation(() => {});
        jest.spyOn(wrapper.vm, 'isDefault').mockReturnValue(false);

        wrapper.vm.translationMetadata = {
            'fr-FR': { locale: 'fr-FR', lastUpdate: '2026-01-01T00:00:00+00:00' },
            'es-ES': { locale: 'es-ES', lastUpdate: null },
        };
        wrapper.vm.snippetSelection = {
            'fr-id': { id: 'fr-id', name: 'Français', locale: { code: 'fr-FR' } },
            'es-id': { id: 'es-id', name: 'Español', locale: { code: 'es-ES' } },
        };

        wrapper.vm.openDeleteModal(wrapper.vm.bulkDeleteLanguages);

        expect(wrapper.vm.deleteCandidates).toHaveLength(2);
        expect(wrapper.vm.deleteCandidateInstalledLocales).toEqual(['fr-FR']);

        wrapper.vm.deleteTranslationFiles = true;
        await wrapper.vm.confirmDelete();

        expect(wrapper.vm.languageRepository.syncDeleted).toHaveBeenCalledWith(
            [
                'fr-id',
                'es-id',
            ],
            expect.anything(),
        );
        expect(wrapper.vm.translationService.deleteTranslation).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.translationService.deleteTranslation).toHaveBeenCalledWith('fr-FR');
        // the grid selection is cleared once the deletion finished
        expect(wrapper.vm.snippetSelection).toEqual({});
    });

    it('excludes the system default language from a bulk delete', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        jest.spyOn(wrapper.vm, 'isDefault').mockImplementation((id) => id === 'default-id');

        wrapper.vm.snippetSelection = {
            a: { id: 'fr-id', name: 'Français', locale: { code: 'fr-FR' } },
            def: { id: 'default-id', name: 'English', locale: { code: 'en-GB' } },
        };

        expect(wrapper.vm.bulkDeleteLanguages.map((language) => language.id)).toEqual(['fr-id']);
    });

    it('lists the delete candidates alphabetically by name', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.deleteCandidates = [
            { id: '1', name: 'Zulu' },
            { id: '2', name: 'Català' },
            { id: '3', name: 'Bosanski' },
        ];

        expect(wrapper.vm.sortedDeleteCandidates.map((language) => language.name)).toEqual([
            'Bosanski',
            'Català',
            'Zulu',
        ]);
    });
});
