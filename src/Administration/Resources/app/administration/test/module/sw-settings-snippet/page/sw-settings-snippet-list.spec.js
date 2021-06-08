import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';
import 'src/module/sw-settings-snippet/page/sw-settings-snippet-list';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-menu';

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
                    value: 'Neue Adresse hinzufügen'
                },
                {
                    author: 'Shopware',
                    id: null,
                    origin: 'Add address',
                    resetTo: 'Add address',
                    setId: 'e54dba2ba96741868e6b6642504c6932',
                    translationKey: 'account.addressCreateBtn',
                    value: 'Add address'
                }
            ]
        }
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
            name: 'BASE de-DE'
        }
    ];

    data.total = data.length;

    return data;
}

describe('module/sw-settings-snippet/page/sw-settings-snippet-list', () => {
    function createWrapper(privileges = []) {
        const localVue = createLocalVue();
        localVue.directive('tooltip', {});

        return shallowMount(Shopware.Component.build('sw-settings-snippet-list'), {
            localVue,
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve(getSnippetSets())
                    })
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    }
                },
                userService: {
                    getUser: () => Promise.resolve()
                },
                snippetSetService: {
                    getAuthors: () => {
                        return Promise.resolve();
                    },
                    getCustomList: () => {
                        return Promise.resolve(getSnippets());
                    }
                },
                snippetService: {
                    save: () => Promise.resolve(),
                    delete: () => Promise.resolve(),
                    getFilter: () => Promise.resolve()
                }
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'test'
                        }
                    },
                    query: {
                        ids: 'a2f95068665e4498ae98a2318a7963df'
                    }
                }
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <div class="smart-bar__actions">
                            <slot name="smart-bar-actions"></slot>
                        </div>
                        <slot name="content"></slot>
                    </div>`
                },
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-pagination': true,
                'sw-data-grid-skeleton': true,
                'sw-icon': true,
                'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
                'sw-context-menu': Shopware.Component.build('sw-context-menu'),
                'sw-context-button': Shopware.Component.build('sw-context-button'),
                'sw-data-grid-settings': true,
                'router-link': true,
                'sw-popover': true,
                'sw-button': true
            }
        });
    }

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        [true, 'snippet.viewer'],
        [true, 'snippet.viewer, snippet.editor'],
        [true, 'snippet.viewer, snippet.editor, snippet.creator'],
        [false, 'snippet.viewer, snippet.editor, snippet.deleter']
    ])('should have a reset button with an disabled state of %p with the roles: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = createWrapper(roles);

        await wrapper.vm.$nextTick();

        const contextMenuButton = wrapper.find('.sw-data-grid__row--0 .sw-context-button__button');
        contextMenuButton.trigger('click');

        await wrapper.vm.$nextTick();

        const resetButton = wrapper.find('.sw-settings-snippet-list__delete-action');

        if (!state) {
            expect(resetButton.classes()).not.toContain('is--disabled');

            return;
        }

        expect(resetButton.classes()).toContain('is--disabled');
    });

    it.each([
        ['true', 'snippet.viewer'],
        ['true', 'snippet.viewer, snippet.editor'],
        [undefined, 'snippet.viewer, snippet.editor, snippet.creator'],
        ['true', 'snippet.viewer, snippet.editor, snippet.deleter']
    ])('should have a disabled state of %p on the new snippet button when using role: %s', async (state, role) => {
        const roles = role.split(', ');

        const wrapper = createWrapper(roles);
        wrapper.setData({ isLoading: false });

        await wrapper.vm.$nextTick();

        const createSnippetButton = wrapper.find('.smart-bar__actions sw-button-stub');

        expect(createSnippetButton.attributes('disabled')).toBe(state);
    });
});
