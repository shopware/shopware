/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';

function getSnippetSets() {
    return [
        {
            name: 'messages.en-GB',
            iso: 'en-GB',
            path: 'development/platform/src/Core/Framework/Resources/snippet/en_GB/messages.en-GB.base.json',
            author: 'Shopware',
            isBase: true,
        },
        {
            name: 'messages.de-DE',
            iso: 'de-DE',
            path: 'development/platform/src/Core/Framework/Resources/snippet/de_DE/messages.de-DE.base.json',
            author: 'Shopware',
            isBase: true,
        },
    ];
}

function getSnippetSetData() {
    const data = [
        {
            apiAlias: null,
            baseFile: 'messages.de-DE',
            createdAt: '2020-09-09T07:46:37.407+00:00',
            customFields: null,
            id: 'a2f95068665e4498ae98a2318a7963df',
            iso: 'de-DE',
            name: 'BASE de-DE',
            salesChannelDomains: [],
            snippets: [],
            updatedAt: null,
        },
    ];

    data.total = data.length;

    return data;
}

describe('module/sw-settings-snippet/page/sw-settings-snippet-set-list', () => {
    const saveSpy = jest.fn(() => Promise.resolve());
    async function createWrapper(privileges = []) {
        return mount(
            await wrapTestComponent('sw-settings-snippet-set-list', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    mocks: {
                        $route: {
                            query: 'test',
                        },
                        $t: (key) => key,
                    },
                    provide: {
                        acl: {
                            can: (identifier) => {
                                if (!identifier) {
                                    return true;
                                }

                                return privileges.includes(identifier);
                            },
                        },
                        snippetSetService: {
                            getBaseFiles: () => {
                                return Promise.resolve({
                                    items: getSnippetSets(),
                                });
                            },
                        },
                        repositoryFactory: {
                            create: () => ({
                                create: () => Promise.resolve(getSnippetSetData()[0]),
                                search: () => Promise.resolve(getSnippetSetData()),
                                save: saveSpy,
                            }),
                        },
                        searchRankingService: {},
                    },
                    stubs: {
                        'sw-page': {
                            template: `<div class="sw-page">
                                <slot name="smart-bar-actions"></slot>
                                <slot name="content"></slot>
                            </div>`,
                        },
                        'mt-card': {
                            template: '<div><slot></slot><slot name="grid"></slot></div>',
                        },
                        'sw-card-view': {
                            template: '<div><slot></slot></div>',
                        },
                        'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                        'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                        'sw-confirm-modal': await wrapTestComponent('sw-confirm-modal'),
                        'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                        'sw-pagination': true,
                        'sw-popover': true,
                        'sw-search-bar': true,
                        'sw-bulk-edit-modal': true,
                        'sw-context-button': true,
                        'sw-data-grid-settings': true,
                        'sw-data-grid-column-boolean': true,
                        'sw-data-grid-inline-edit': true,
                        'sw-data-grid-skeleton': true,
                        'sw-provide': true,
                        'mt-select': true,
                        'mt-text-field': true,
                        'router-link': true,
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
            false,
            'snippet.viewer, snippet.editor, snippet.creator',
        ],
        [
            true,
            'snippet.viewer, snippet.editor, snippet.deleter',
        ],
    ])('should have a create snippet set button with a disabled state of %p when having role: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = await createWrapper(roles);

        await flushPromises();

        const createSetButton = wrapper.find('.sw-settings-snippet-set-list__action-add');

        expect(createSetButton.attributes('disabled') !== undefined).toBe(state);
    });

    it('should add a new snippet set', async () => {
        const wrapper = await createWrapper(['snippet.creator']);
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalledWith(
            expect.objectContaining({ name: 'sw-settings-snippet.setList.newSnippetName' }),
        );

        const createSetButton = wrapper.findByText('button', 'sw-settings-snippet.setList.buttonAddSet');
        await createSetButton.trigger('click');

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({ name: 'sw-settings-snippet.setList.newSnippetName' }),
        );
    });

    it('should add a new snippet set twice with unique names', async () => {
        const wrapper = await createWrapper(['snippet.creator']);
        await flushPromises();

        wrapper.vm.snippetSets = [
            ...wrapper.vm.snippetSets,
            {
                name: 'sw-settings-snippet.setList.newSnippetName',
                iso: 'de-DE',
                path: 'development/platform/src/Core/Framework/Resources/snippet/de_DE/messages.de-DE.base.json',
                author: 'Shopware',
                isBase: true,
            },
        ];

        const createSetButton = wrapper.findByText('button', 'sw-settings-snippet.setList.buttonAddSet');
        await createSetButton.trigger('click');

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({ name: `sw-settings-snippet.setList.newSnippetName (2)` }),
        );
    });
});
