/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';

function getSnippets() {
    const data = {
        data: {
            'account.addressCreateBtn': [
                {
                    author: 'Shopware',
                    id: null,
                    origin: 'Neue Adresse hinzufügen',
                    resetTo: 'Neue Adresse hinzufügen',
                    setId: 'a2f95068665e4498ae98a2318a7963df',
                    translationKey: 'account.addressCreateBtn',
                    value: 'Neue Adresse hinzufügen',
                },
                {
                    author: 'Shopware',
                    id: null,
                    origin: 'Add address',
                    resetTo: 'Add address',
                    setId: 'e54dba2ba96741868e6b6642504c6932',
                    translationKey: 'account.addressCreateBtn',
                    value: 'Add address',
                },
            ],
        },
    };

    const totalAmountOfSnippets = Object.keys(data.data).length;
    data.total = totalAmountOfSnippets;

    return data;
}

function getSnippetSets() {
    const data = [
        {
            baseFile: 'messages.de-DE',
            id: 'a2f95068665e4498ae98a2318a7963df',
            iso: 'de-DE',
            name: 'BASE de-DE',
        },
    ];

    data.total = data.length;

    return data;
}

describe('module/sw-settings-snippet/page/sw-settings-snippet-list', () => {
    async function createWrapper(privileges = []) {
        return mount(
            await wrapTestComponent('sw-settings-snippet-list', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        repositoryFactory: {
                            create: () => ({
                                search: () => Promise.resolve(getSnippetSets()),
                            }),
                        },
                        acl: {
                            can: (identifier) => {
                                if (!identifier) {
                                    return true;
                                }

                                return privileges.includes(identifier);
                            },
                        },
                        userService: {
                            getUser: () =>
                                Promise.resolve({
                                    data: { username: 'admin' },
                                }),
                        },
                        snippetSetService: {
                            getAuthors: () => {
                                return Promise.resolve({ data: [] });
                            },
                            getCustomList: () => {
                                return Promise.resolve(getSnippets());
                            },
                        },
                        snippetService: {
                            save: () => Promise.resolve(),
                            delete: () => Promise.resolve(),
                            getFilter: () => Promise.resolve({ data: [] }),
                        },
                        searchRankingService: {},
                        userConfigService: {
                            search: () => ({ data: [] }),
                            upsert: () => null,
                        },
                    },
                    mocks: {
                        $route: {
                            meta: {
                                $module: {
                                    icon: 'test',
                                },
                            },
                            query: {
                                ids: 'a2f95068665e4498ae98a2318a7963df',
                            },
                        },
                    },
                    stubs: {
                        'sw-page': {
                            template: `
                    <div class="sw-page">
                        <div class="smart-bar__actions">
                            <slot name="smart-bar-actions"></slot>
                        </div>
                        <slot name="content"></slot>
                    </div>`,
                        },
                        'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                        'sw-pagination': true,
                        'sw-data-grid-skeleton': true,
                        'sw-icon': true,
                        'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                        'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                        'sw-context-button': await wrapTestComponent('sw-context-button'),
                        'sw-data-grid-settings': true,
                        'router-link': true,
                        'sw-popover': true,
                        'sw-button': true,
                        'sw-search-bar': true,
                        'sw-text-field': true,
                        'sw-grid-column': true,
                        'sw-grid': true,
                        'sw-settings-snippet-sidebar': true,
                        'sw-checkbox-field': true,
                        'sw-data-grid-column-boolean': true,
                        'sw-data-grid-inline-edit': true,
                    },
                },
            },
        );
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        [
            true,
            'snippet.viewer',
        ],
        [
            true,
            'snippet.viewer, snippet.editor',
        ],
        [
            true,
            'snippet.viewer, snippet.editor, snippet.creator',
        ],
        [
            false,
            'snippet.viewer, snippet.editor, snippet.deleter',
        ],
    ])('should have a reset button with an disabled state of %p with the roles: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = await createWrapper(roles);

        await flushPromises();

        const contextMenuButton = wrapper.find('.sw-data-grid__row--0 .sw-context-button__button');
        await contextMenuButton.trigger('click');

        await flushPromises();

        const resetButton = wrapper.find('.sw-settings-snippet-list__delete-action');

        if (!state) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(resetButton.classes()).not.toContain('is--disabled');

            return;
        }

        expect(resetButton.classes()).toContain('is--disabled');
    });

    it.each([
        [
            'true',
            'snippet.viewer',
        ],
        [
            'true',
            'snippet.viewer, snippet.editor',
        ],
        [
            undefined,
            'snippet.viewer, snippet.editor, snippet.creator',
        ],
        [
            'true',
            'snippet.viewer, snippet.editor, snippet.deleter',
        ],
    ])('should have a disabled state of %p on the new snippet button when using role: %s', async (state, role) => {
        const roles = role.split(', ');

        const wrapper = await createWrapper(roles);
        await wrapper.setData({ isLoading: false });

        await wrapper.vm.$nextTick();

        const createSnippetButton = wrapper.find('.smart-bar__actions sw-button-stub');

        expect(createSnippetButton.attributes('disabled')).toBe(state);
    });
});
