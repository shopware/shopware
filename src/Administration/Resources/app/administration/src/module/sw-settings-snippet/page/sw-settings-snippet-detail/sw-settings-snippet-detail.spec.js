/**
 * @sw-package fundamentals@discovery
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

const saveMock = jest.fn(() => Promise.resolve());

describe('module/sw-settings-snippet/page/sw-settings-snippet-detail', () => {
    async function createWrapper(privileges = []) {
        return mount(
            await wrapTestComponent('sw-settings-snippet-detail', {
                sync: true,
            }),
            {
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
                                save: saveMock,
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
                        'sw-card-view': await wrapTestComponent('sw-card-view'),
                        'sw-text-field': await wrapTestComponent('sw-text-field'),
                        'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                        'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                        'sw-block-field': await wrapTestComponent('sw-block-field'),
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-field-error': await wrapTestComponent('sw-field-error'),
                        'sw-button-process': await wrapTestComponent('sw-button-process'),
                        'sw-skeleton': true,
                        'sw-search-bar': true,
                        'router-link': true,
                        'sw-app-actions': true,
                        'sw-loader': true,
                        'sw-error-summary': true,
                        'sw-app-topbar-button': true,
                        'sw-app-topbar-sidebar': true,
                        'sw-notification-center': true,
                        'sw-help-center-v2': true,
                        'sw-context-menu-item': true,
                        'sw-context-button': true,
                        'sw-extension-component-section': true,
                        'sw-ai-copilot-badge': true,
                        'sw-field-copyable': true,
                        'sw-inheritance-switch': true,
                        'sw-help-text': true,
                    },
                },
            },
        );
    }

    beforeEach(() => {
        Shopware.Store.get('session').setCurrentUser({ username: 'admin' });
    });

    it.each([
        [
            '',
            'snippet.viewer',
        ],
        [
            undefined,
            'snippet.viewer, snippet.editor',
        ],
        [
            undefined,
            'snippet.viewer, snippet.editor, snippet.creator',
        ],
        [
            undefined,
            'snippet.viewer, snippet.editor, snippet.deleter',
        ],
    ])('should only have disabled inputs', async (state, role) => {
        Shopware.Store.get('session').setCurrentUser({
            username: 'testUser',
        });
        const roles = role.split(', ');
        const wrapper = await createWrapper(roles);
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
        });
        await flushPromises();

        const [
            firstInput,
            secondInput,
        ] = wrapper.findAll('input[aria-label="sw-settings-snippet.detail.labelContent"]');

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
        const wrapper = await createWrapper([
            'snippet.viewer',
            'snippet.editor',
            'snippet.creator',
        ]);
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

    it('should skip non-saveable snippets', async () => {
        const wrapper = await createWrapper('snippet.viewer');
        wrapper.vm.snippets = [
            {
                value: 'foo',
                origin: null,
            },
            {
                value: null,
                origin: null,
            },
            {
                value: null,
                origin: 'bar',
            },
            {
                value: ' ',
                origin: null,
            },
        ];

        wrapper.vm.onSave();

        expect(saveMock).toHaveBeenCalledTimes(3);
    });

    describe('getSnippetState', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
        });

        // PHP always sets origin='' for DB records. resetTo holds the file value (if any),
        // or the DB value itself for DB-only snippets (resetTo === value → no file).
        // _hasFileValue is set at load time to reliably track this even after the value changes.

        it('returns "inherited" when _pendingDelete is set (pending restore via onResetSnippet)', () => {
            const snippet = {
                id: 'some-id',
                value: null,
                resetTo: 'file val',
                origin: '',
                _hasFileValue: true,
                _pendingDelete: true,
            };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('inherited');
        });

        it('returns "overridden" when DB record has a value that differs from the file value', () => {
            const snippet = { id: 'some-id', value: 'custom', resetTo: 'file val', origin: '', _hasFileValue: true };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('overridden');
        });

        it('returns "overridden" when a file-overridden snippet is cleared to empty string', () => {
            const snippet = { id: 'some-id', value: '', resetTo: 'file val', origin: '', _hasFileValue: true };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('overridden');
        });

        it('returns "empty" when a DB-only snippet is cleared to empty string (no file to inherit)', () => {
            const snippet = { id: 'some-id', value: '', resetTo: 'db val', origin: '', _hasFileValue: false };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('empty');
        });

        it('returns "custom" for DB-only snippet where PHP echoes resetTo=value (no file exists)', () => {
            const snippet = { id: 'some-id', value: 'db val', resetTo: 'db val', origin: '', _hasFileValue: false };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('custom');
        });

        it('returns "custom" when DB record has value but resetTo is null (no file value)', () => {
            const snippet = { id: 'some-id', value: 'custom', resetTo: null, origin: null, _hasFileValue: false };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('custom');
        });

        it('returns "empty" when no DB record and no file value', () => {
            const snippet = { id: null, value: null, resetTo: null, origin: null, _overriding: false };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('empty');
        });

        it('returns "overriding" when user started editing a file-only snippet', () => {
            const snippet = { id: null, value: null, resetTo: 'file val', origin: 'file val', _overriding: true };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('overriding');
        });

        it('returns "inherited" for a file-only snippet that is not being overridden', () => {
            const snippet = { id: null, value: null, resetTo: 'file val', origin: 'file val', _overriding: false };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('inherited');
        });

        it('returns "custom" when user has typed into a brand-new snippet with no file value', () => {
            // Before fix, this incorrectly returned "inherited", hiding the typed value
            const snippet = { id: null, value: 'typed text', resetTo: null, origin: null, _overriding: false };
            expect(wrapper.vm.getSnippetState(snippet)).toBe('custom');
        });
    });

    describe('getPlaceholder', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await flushPromises();
        });

        it('returns the empty placeholder when snippet state is "empty"', () => {
            const snippet = {
                setId: 'set-1',
                id: 'some-id',
                value: '',
                resetTo: 'file val',
                origin: '',
                _hasFileValue: false,
            };
            wrapper.vm.snippets = [snippet];

            expect(wrapper.vm.getPlaceholder(snippet)).toBe('sw-settings-snippet.general.placeholderValue');
        });

        it('returns resetTo when snippet state is not empty and resetTo is set', () => {
            const snippet = {
                setId: 'set-1',
                id: 'some-id',
                value: 'custom',
                resetTo: 'file val',
                origin: '',
                _hasFileValue: true,
            };
            wrapper.vm.snippets = [snippet];

            expect(wrapper.vm.getPlaceholder(snippet)).toBe('file val');
        });

        it('falls back to origin when resetTo is not set', () => {
            const snippet = {
                setId: 'set-1',
                id: null,
                value: null,
                resetTo: null,
                origin: 'file val',
                _overriding: true,
            };
            wrapper.vm.snippets = [snippet];

            expect(wrapper.vm.getPlaceholder(snippet)).toBe('file val');
        });

        it('falls back to the empty placeholder when neither resetTo nor origin is set', () => {
            const snippet = {
                setId: 'set-1',
                id: null,
                value: null,
                resetTo: null,
                origin: null,
                _overriding: false,
            };
            wrapper.vm.snippets = [snippet];

            expect(wrapper.vm.getPlaceholder(snippet)).toBe('sw-settings-snippet.general.placeholderValue');
        });
    });

    describe('onResetSnippet', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await flushPromises();
        });

        it('clears value and saves it for undo when resetting a DB-overridden snippet', () => {
            // PHP sets origin='' for all DB records; resetTo holds the file value
            const snippet = {
                id: 'some-id',
                value: 'my override',
                resetTo: 'original',
                origin: '',
                _overriding: false,
                _savedValue: null,
                _pendingDelete: false,
            };
            wrapper.vm.snippets = [snippet];
            wrapper.vm.onResetSnippet(snippet);
            expect(snippet.value).toBeNull();
            expect(snippet._savedValue).toBe('my override');
            expect(snippet._pendingDelete).toBe(true);
        });

        it('restores file value and exits overriding mode when resetting a file snippet in edit mode', () => {
            const snippet = {
                id: null,
                value: 'typed text',
                resetTo: null,
                origin: 'file val',
                _overriding: true,
                _savedValue: null,
            };
            wrapper.vm.snippets = [snippet];
            wrapper.vm.onResetSnippet(snippet);
            expect(snippet.value).toBe('file val');
            expect(snippet._overriding).toBe(false);
        });

        it('recalculates isSaveable immediately — pending restore is saveable', () => {
            wrapper.vm.isSaveable = false;
            const snippet = {
                id: 'some-id',
                value: 'my override',
                resetTo: 'original',
                origin: 'original',
                _overriding: false,
                _savedValue: null,
                _pendingDelete: false,
            };
            wrapper.vm.snippets = [snippet];
            wrapper.vm.onResetSnippet(snippet);
            // After reset: _pendingDelete=true → saveable
            expect(wrapper.vm.isSaveable).toBe(true);
        });

        it('recalculates isSaveable immediately — overriding snippet restored to file value stays saveable', () => {
            wrapper.vm.isSaveable = false;
            const snippet = {
                id: null,
                value: 'typed text',
                resetTo: null,
                origin: 'file val',
                _overriding: true,
                _savedValue: null,
            };
            wrapper.vm.snippets = [snippet];
            wrapper.vm.onResetSnippet(snippet);
            // After reset: value='file val', id=null → non-null value → counted as saveable
            expect(wrapper.vm.isSaveable).toBe(true);
        });
    });

    describe('onRemoveInheritance', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await flushPromises();
        });

        it('sets _overriding to true for a file-only snippet', () => {
            const snippet = {
                id: null,
                value: null,
                resetTo: 'file val',
                origin: 'file val',
                _overriding: false,
                _savedValue: null,
                _pendingDelete: false,
            };
            wrapper.vm.snippets = [snippet];
            wrapper.vm.onRemoveInheritance(snippet);
            expect(snippet._overriding).toBe(true);
        });

        it('restores the saved value when undoing a pending restore', () => {
            const snippet = {
                id: 'some-id',
                value: null,
                resetTo: 'original',
                origin: 'original',
                _overriding: false,
                _savedValue: 'my override',
                _pendingDelete: true,
            };
            wrapper.vm.snippets = [snippet];
            wrapper.vm.onRemoveInheritance(snippet);
            expect(snippet.value).toBe('my override');
            expect(snippet._savedValue).toBeNull();
            expect(snippet._pendingDelete).toBe(false);
        });

        it('recalculates isSaveable immediately after removing inheritance', () => {
            wrapper.vm.isSaveable = false;
            const snippet = {
                id: 'some-id',
                value: null,
                resetTo: 'original',
                origin: 'original',
                _overriding: false,
                _savedValue: 'my override',
                _pendingDelete: true,
            };
            wrapper.vm.snippets = [snippet];
            wrapper.vm.onRemoveInheritance(snippet);
            // After undo: value='my override', id set → non-null value → saveable
            expect(wrapper.vm.isSaveable).toBe(true);
        });
    });

    describe('checkIsSaveable', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await flushPromises();
            wrapper.vm.translationKey = 'account.addressCreateBtn';
            wrapper.vm.translationKeyOrigin = 'account.addressCreateBtn';
        });

        it('returns true when a snippet has a pending restore (_pendingDelete set)', () => {
            wrapper.vm.snippets = [{ id: 'some-id', value: null, _pendingDelete: true }];
            expect(wrapper.vm.checkIsSaveable()).toBe(true);
        });

        it('returns false when all snippets have null value and no DB record', () => {
            wrapper.vm.snippets = [{ id: null, value: null }];
            expect(wrapper.vm.checkIsSaveable()).toBe(false);
        });

        it('returns true when at least one snippet has a non-null value', () => {
            wrapper.vm.snippets = [
                { id: null, value: null },
                { id: null, value: 'some text' },
            ];
            expect(wrapper.vm.checkIsSaveable()).toBe(true);
        });
    });

    it('resets isSaveable to true when prepareContent reloads (guards against stale debounce)', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.isSaveable = false;
        wrapper.vm.prepareContent();
        expect(wrapper.vm.isSaveable).toBe(true);
    });

    describe('onChange', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await flushPromises();
        });

        it('assigns value to the snippet', () => {
            const snippet = { id: null, value: null };
            wrapper.vm.onChange(snippet, 'new value');
            expect(snippet.value).toBe('new value');
        });

        it('sets isSaveable false and isInvalidKey true when translationKey is empty', () => {
            wrapper.vm.translationKey = '';
            const snippet = { id: null, value: null };
            wrapper.vm.onChange(snippet, 'x');
            expect(wrapper.vm.isSaveable).toBe(false);
            expect(wrapper.vm.isInvalidKey).toBe(true);
        });

        it('works without arguments (legacy call from translation key field)', () => {
            wrapper.vm.translationKey = 'some.key';
            expect(() => wrapper.vm.onChange()).not.toThrow();
        });
    });

    describe('onSave pending delete', () => {
        const deleteMock = jest.fn(() => Promise.resolve());

        async function createWrapperWithDelete() {
            return mount(await wrapTestComponent('sw-settings-snippet-detail', { sync: true }), {
                global: {
                    mocks: {
                        $route: {
                            meta: { $module: { color: 'blue', icon: 'icon' } },
                            query: { page: 1, limit: 25, ids: [] },
                            params: { key: 'account.addressCreateBtn' },
                        },
                    },
                    provide: {
                        repositoryFactory: {
                            create: () => ({
                                search: () => Promise.resolve(getSnippetSets()),
                                create: () => Promise.resolve(),
                                save: saveMock,
                                delete: deleteMock,
                            }),
                        },
                        acl: { can: () => true },
                        userService: {},
                        snippetSetService: {
                            getAuthors: () => Promise.resolve(),
                            getCustomList: () => Promise.resolve(getSnippets()),
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
                        'sw-card-view': await wrapTestComponent('sw-card-view'),
                        'sw-text-field': await wrapTestComponent('sw-text-field'),
                        'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                        'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                        'sw-block-field': await wrapTestComponent('sw-block-field'),
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-field-error': await wrapTestComponent('sw-field-error'),
                        'sw-button-process': await wrapTestComponent('sw-button-process'),
                        'sw-skeleton': true,
                        'sw-search-bar': true,
                        'router-link': true,
                        'sw-app-actions': true,
                        'sw-loader': true,
                        'sw-error-summary': true,
                        'sw-app-topbar-button': true,
                        'sw-app-topbar-sidebar': true,
                        'sw-notification-center': true,
                        'sw-help-center-v2': true,
                        'sw-context-menu-item': true,
                        'sw-context-button': true,
                        'sw-extension-component-section': true,
                        'sw-ai-copilot-badge': true,
                        'sw-field-copyable': true,
                        'sw-inheritance-switch': true,
                        'sw-help-text': true,
                    },
                },
            });
        }

        beforeEach(() => {
            deleteMock.mockClear();
            saveMock.mockClear();
            Shopware.Store.get('session').setCurrentUser({ username: 'admin' });
        });

        it('deletes the DB record when _pendingDelete is set (pending restore, file-overridden)', async () => {
            const wrapper = await createWrapperWithDelete();
            await flushPromises();
            wrapper.vm.snippets = [
                {
                    id: 'some-id',
                    value: null,
                    origin: 'file val',
                    _pendingDelete: true,
                    author: 'user/admin',
                    translationKey: 'account.addressCreateBtn',
                    setId: 'a',
                },
                {
                    id: null,
                    value: null,
                    origin: null,
                    _pendingDelete: false,
                    author: 'user/admin',
                    translationKey: 'account.addressCreateBtn',
                    setId: 'b',
                },
            ];
            wrapper.vm.isSaveable = true;
            wrapper.vm.onSave();
            expect(deleteMock).toHaveBeenCalledWith('some-id');
            expect(saveMock).not.toHaveBeenCalled();
        });

        it('deletes the DB record when _pendingDelete is set (pending restore, DB-only with empty origin)', async () => {
            const wrapper = await createWrapperWithDelete();
            await flushPromises();
            wrapper.vm.snippets = [
                {
                    id: 'some-id',
                    value: null,
                    origin: '',
                    _pendingDelete: true,
                    author: 'user/admin',
                    translationKey: 'account.addressCreateBtn',
                    setId: 'a',
                },
            ];
            wrapper.vm.isSaveable = true;
            wrapper.vm.onSave();
            expect(deleteMock).toHaveBeenCalledWith('some-id');
        });

        it('skips snippet with no DB record and no file value', async () => {
            const wrapper = await createWrapperWithDelete();
            await flushPromises();
            wrapper.vm.snippets = [
                {
                    id: null,
                    value: null,
                    origin: null,
                    author: 'user/admin',
                    translationKey: 'account.addressCreateBtn',
                    setId: 'a',
                },
            ];
            wrapper.vm.isSaveable = true;
            wrapper.vm.onSave();
            expect(deleteMock).not.toHaveBeenCalled();
            expect(saveMock).not.toHaveBeenCalled();
        });
    });
});
