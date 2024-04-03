/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils';

function getSnippetSets() {
    const data = [
        {
            name: 'BASE de-DE',
            baseFile: 'messages.de-DE',
            iso: 'de-DE',
            customFields: null,
            createdAt: '2020-09-09T07:46:37.407+00:00',
            updatedAt: null,
            apiAlias: null,
            id: 'a2f95068665e4498ae98a2318a7963df',
            snippets: [],
            salesChannelDomains: [],
        },
        {
            name: 'BASE en-GB',
            baseFile: 'messages.en-GB',
            iso: 'en-GB',
            customFields: null,
            createdAt: '2020-09-09T07:46:37.407+00:00',
            updatedAt: null,
            apiAlias: null,
            id: 'e54dba2ba96741868e6b6642504c6932',
            snippets: [],
            salesChannelDomains: [],
        },
    ];

    data.total = data.length;

    data.get = () => {
        return false;
    };

    return data;
}

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
            test1: [
                {
                    author: 'Shopware',
                    id: null,
                    origin: 'foo',
                    resetTo: 'foo',
                    setId: 'a2f95068665e4498ae98a2318a7963df',
                    translationKey: 'test1',
                    value: 'foo',
                },
                {
                    author: 'Shopware',
                    id: null,
                    origin: 'bar',
                    resetTo: 'bar',
                    setId: 'e54dba2ba96741868e6b6642504c6932',
                    translationKey: 'test1',
                    value: 'bar',
                },
            ],
        },
    };

    const totalAmountOfSnippets = Object.keys(data.data).length;
    data.total = totalAmountOfSnippets;

    return data;
}

describe('module/sw-settings-snippet/page/sw-settings-snippet-detail', () => {
    async function createWrapper(privileges = []) {
        return mount(await wrapTestComponent('sw-settings-snippet-detail', {
            sync: true,
        }), {
            global: {
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                color: 'blue',
                                icon: 'icon',
                            },
                        },
                        query: {
                            page: 1,
                            limit: 25,
                            ids: [],
                        },
                        params: {
                            key: 'account.addressCreateBtn',
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve(getSnippetSets()),
                            create: () => Promise.resolve(),
                            save: () => Promise.resolve(),
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
                    userService: {},
                    snippetSetService: {
                        getAuthors: () => {
                            return Promise.resolve();
                        },
                        getCustomList: () => {
                            return Promise.resolve(getSnippets());
                        },
                    },
                    snippetService: {
                        save: () => Promise.resolve(),
                        delete: () => Promise.resolve(),
                        getFilter: () => Promise.resolve(),
                    },
                    validationService: {},
                },
                stubs: {
                    'sw-page': await wrapTestComponent('sw-page'),
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-button-process': await wrapTestComponent('sw-button-process'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-skeleton': true,
                },
            },
        });
    }

    beforeEach(() => {
        Shopware.State.commit('setCurrentUser', { username: 'admin' });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        ['', 'snippet.viewer'],
        [undefined, 'snippet.viewer, snippet.editor'],
        [undefined, 'snippet.viewer, snippet.editor, snippet.creator'],
        [undefined, 'snippet.viewer, snippet.editor, snippet.deleter'],
    ])('should only have disabled inputs', async (state, role) => {
        Shopware.State.get('session').currentUser = {
            username: 'testUser',
        };
        const roles = role.split(', ');
        const wrapper = await createWrapper(roles);
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
        });
        await flushPromises();

        const [firstInput, secondInput] = wrapper.findAll('input[label="sw-settings-snippet.detail.labelContent"]');

        expect(firstInput.attributes('disabled')).toBe(state);
        expect(secondInput.attributes('disabled')).toBe(state);
    });

    it('should have a disabled save button', async () => {
        const wrapper = await createWrapper('snippet.viewer');
        await flushPromises();

        const saveButton = wrapper.find('.sw-snippet-detail__save-action');

        expect(saveButton.attributes()).toHaveProperty('disabled');
    });

    it('should change translationKey while saving', async () => {
        const wrapper = await createWrapper(['snippet.viewer', 'snippet.editor', 'snippet.creator']);
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
            isAddedSnippet: true,
        });
        await flushPromises();

        const translationKeyInput = wrapper.find('input[name="sw-field--translationKey"]');
        expect(translationKeyInput.attributes()).not.toHaveProperty('disabled');
        await translationKeyInput.setValue('test1');
        await translationKeyInput.trigger('update:value');
        await flushPromises();

        const saveButton = wrapper.find('.sw-snippet-detail__save-action');
        expect(saveButton.attributes()).not.toHaveProperty('disabled');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.translationKey).toBe('test1');
        expect(wrapper.vm.translationKeyOrigin).toBe('test1');
        expect(wrapper.vm.$route.params.key).toBe('test1');
    });

    it('should return a criteria with no limit', async () => {
        const wrapper = await createWrapper('snippet.viewer');
        const criteria = wrapper.vm.snippetSetCriteria;

        expect(criteria).toStrictEqual(
            expect.objectContaining({
                limit: null,
                page: 1,
            }),
        );
    });
});
