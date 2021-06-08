import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';
import 'src/module/sw-settings-snippet/page/sw-settings-snippet-detail';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

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
            salesChannelDomains: []
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
            salesChannelDomains: []
        }
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

describe('module/sw-settings-snippet/page/sw-settings-snippet-detail', () => {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    function createWrapper(privileges = []) {
        return shallowMount(Shopware.Component.build('sw-settings-snippet-detail'), {
            localVue,
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            color: 'blue',
                            icon: 'icon'
                        }
                    },
                    query: {
                        page: 1,
                        limit: 25,
                        ids: []
                    },
                    params: {
                        key: 'account.addressCreateBtn'
                    }
                }
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve(getSnippetSets()),
                        create: () => Promise.resolve()
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
                userService: {},
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
                },
                validationService: {}
            },
            stubs: {
                'sw-page': {
                    template: '<div class="sw-page"><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>'
                },
                'sw-card': {
                    template: '<div><slot></slot><slot name="grid"></slot></div>'
                },
                'sw-card-view': {
                    template: '<div><slot></slot></div>'
                },
                'sw-field': Shopware.Component.build('sw-field'),
                'sw-text-field': Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-button-process': true,
                'sw-button': true
            }
        });
    }

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        ['disabled', 'snippet.viewer'],
        [undefined, 'snippet.viewer, snippet.editor'],
        [undefined, 'snippet.viewer, snippet.editor, snippet.creator'],
        [undefined, 'snippet.viewer, snippet.editor, snippet.deleter']
    ])('should only have disabled inputs', async (state, role) => {
        Shopware.State.get('session').currentUser = {
            username: 'testUser'
        };
        const roles = role.split(', ');
        const wrapper = createWrapper(roles);

        await wrapper.vm.$nextTick();

        const [firstInput, secondInput] = wrapper.findAll('input[name=sw-field--snippet-value]').wrappers;

        expect(firstInput.attributes('disabled')).toBe(state);
        expect(secondInput.attributes('disabled')).toBe(state);
    });

    it('should have a disabled save button', () => {
        const wrapper = createWrapper('snippet.viewer');
        const saveButton = wrapper.find('.sw-snippet-detail__save-action');

        expect(saveButton.attributes('disabled')).toContain('true');
    });
});
