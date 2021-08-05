import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';
import 'src/module/sw-settings-snippet/page/sw-settings-snippet-set-list';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/grid/sw-grid-column';
import 'src/app/component/grid/sw-grid-row';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-menu';

function getSnippetSets() {
    return [
        {
            name: 'messages.en-GB',
            iso: 'en-GB',
            path: 'development/platform/src/Core/Framework/Resources/snippet/en_GB/messages.en-GB.base.json',
            author: 'Shopware',
            isBase: true
        },
        {
            name: 'messages.de-DE',
            iso: 'de-DE',
            path: 'development/platform/src/Core/Framework/Resources/snippet/de_DE/messages.de-DE.base.json',
            author: 'Shopware',
            isBase: true
        }
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
            updatedAt: null
        }
    ];

    data.total = data.length;

    return data;
}

describe('module/sw-settings-snippet/page/sw-settings-snippet-set-list', () => {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('tooltip', {});
    localVue.filter('date', () => {});

    function createWrapper(privileges = []) {
        return shallowMount(Shopware.Component.build('sw-settings-snippet-set-list'), {
            localVue,
            mocks: {
                $route: {
                    query: 'test'
                }
            },
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    }
                },
                snippetSetService: {
                    getBaseFiles: () => {
                        return Promise.resolve({ items: getSnippetSets() });
                    }
                },
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve(getSnippetSetData())
                    })
                }
            },
            stubs: {
                'sw-page': {
                    template: '<div class="sw-page"><slot name="content"></slot></div>'
                },
                'sw-icon': true,
                'sw-button': true,
                'sw-card': {
                    template: '<div><slot></slot><slot name="grid"></slot></div>'
                },
                'sw-card-view': {
                    template: '<div><slot></slot></div>'
                },
                'sw-button-group': true,
                'sw-container': {
                    template: '<div><slot></slot></div>'
                },
                'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
                'sw-context-menu': Shopware.Component.build('sw-context-menu'),
                'sw-context-button': Shopware.Component.build('sw-context-button'),
                'sw-context-menu-divider': true,
                'sw-card-section': true,
                'sw-pagination': true,
                'sw-grid': Shopware.Component.build('sw-grid'),
                'sw-field': true,
                'sw-grid-row': Shopware.Component.build('sw-grid-row'),
                'sw-grid-column': Shopware.Component.build('sw-grid-column'),
                'router-link': true,
                'sw-popover': true
            }
        });
    }

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        ['snippet.viewer', false],
        ['snippet.viewer, snippet.editor', true],
        ['snippet.viewer, snippet.editor, snippet.editor', true],
        ['snippet.viewer, snippet.editor, snippet.deleter', true]
    ])('should display checkboxes depending on role: %s', async (role, displayCheckboxes) => {
        const roles = role.split(', ');
        const wrapper = createWrapper(roles);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const gridCheckboxes = wrapper.find('.sw-grid .sw-grid__header sw-field-stub[type="checkbox"]');

        expect(gridCheckboxes.exists()).toBe(displayCheckboxes);
    });

    it.each([
        ['true', 'snippet.viewer'],
        ['true', 'snippet.viewer, snippet.editor'],
        [undefined, 'snippet.viewer, snippet.editor, snippet.creator'],
        ['true', 'snippet.viewer, snippet.editor, snippet.deleter']
    ])('should have a create snippet set button with a disabled state of %p when having role: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = createWrapper(roles);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const createSetButton = wrapper.find('.sw-settings-snippet-set-list__action-add');

        expect(createSetButton.attributes('disabled')).toBe(state);
    });

    it.each([
        [true, 'snippet.viewer'],
        [true, 'snippet.viewer, snippet.editor'],
        [true, 'snippet.viewer, snippet.editor, snippet.creator'],
        [false, 'snippet.viewer, snippet.editor, snippet.deleter']
    ])('should have a delete button with a disabled state of %p when having role: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = createWrapper(roles);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const contextMenuButton = wrapper.find('.sw-grid__row--0 .sw-context-button');
        contextMenuButton.trigger('click');

        await wrapper.vm.$nextTick();

        // open context menu button
        const contextMenuItems = wrapper.findAll('.sw-context-menu-item').wrappers;
        const [,, deleteButton] = contextMenuItems;

        if (!state) {
            expect(deleteButton.classes()).not.toContain('is--disabled');

            return;
        }

        expect(deleteButton.classes()).toContain('is--disabled');
    });

    it.each([
        [true, 'snippet.viewer'],
        [true, 'snippet.viewer, snippet.editor'],
        [false, 'snippet.viewer, snippet.editor, snippet.creator'],
        [true, 'snippet.viewer, snippet.editor, snippet.deleter']
    ])('should have a duplicate button with the disabled state of %p when having role: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = createWrapper(roles);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const contextMenuButton = wrapper.find('.sw-grid__row--0 .sw-context-button');

        // open context menu button
        contextMenuButton.trigger('click');

        await wrapper.vm.$nextTick();

        const contextMenuItems = wrapper.findAll('.sw-context-menu-item').wrappers;
        const [, duplicateButton] = contextMenuItems;

        if (!state) {
            expect(duplicateButton.classes()).not.toContain('is--disabled');

            return;
        }

        expect(duplicateButton.classes()).toContain('is--disabled');
    });
});
