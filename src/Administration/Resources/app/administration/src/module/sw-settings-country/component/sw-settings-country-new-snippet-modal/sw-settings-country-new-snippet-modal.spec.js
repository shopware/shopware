import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */
async function createWrapper(customPropsData = {}) {
    return mount(await wrapTestComponent('sw-settings-country-new-snippet-modal', {
        sync: true,
    }), {
        props: {
            selections: [
                {
                    id: 'symbol/dash',
                    name: '-',
                    parentId: 'symbol',
                },
                {
                    id: 'symbol/comma',
                    name: ',',
                    parentId: 'symbol',
                },
                {
                    id: 'address/country_state',
                    name: 'Country State',
                    parentId: null,
                },
                {
                    id: 'address/salutation',
                    name: 'Salutation',
                    parentId: null,
                },
                {
                    id: 'address/country',
                    name: 'Country',
                    parentId: null,
                },
                {
                    id: 'address/zipcode',
                    name: 'Zip code',
                    parentId: null,
                },
                {
                    id: 'address/first_name',
                    name: 'First name',
                    parentId: null,
                },
            ],
            currentPosition: 0,
            addressFormat: [
                ['address/company', 'symbol/dash', 'address/department'],
            ],
            ...customPropsData,
        },

        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $tc: key => key,
                $route: {
                    params: {
                        id: 'id',
                    },
                },
                $device: {
                    getSystemKey: () => {},
                    onResize: () => {},
                },
            },

            provide: {
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
            },

            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-icon': true,
                'sw-tree': await wrapTestComponent('sw-tree'),
                'sw-tree-item': await wrapTestComponent('sw-tree-item'),
                'sw-tree-input-field': await wrapTestComponent('sw-tree-input-field'),
                'sw-confirm-field': true,
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-context-menu-item': {
                    template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`,
                },
                'sw-vnode-renderer': await wrapTestComponent('sw-vnode-renderer'),
                'sw-skeleton': true,
                'sw-checkbox-field': true,
            },
        },
    });
}

describe('src/module/sw-settings-country/component/sw-settings-country-new-snippet-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to remove the snippet', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const button = wrapper.find('.sw-select-selection-list__item-holder--0 > span');

        await button.find('.sw-label__dismiss').trigger('click');

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0]).toEqual([
            0,
            ['symbol/dash', 'address/department'],
        ]);
    });

    it('should be able to add new snippet', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const treeItemChildren = wrapper.find('.tree-items .sw-tree-item__children');

        await treeItemChildren.find('.sw-tree-item__element .sw-button').trigger('click');

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0]).toEqual([
            0,
            ['address/company', 'symbol/dash', 'address/department', 'symbol/dash'],
        ]);
    });

    it('should be able to reorder data when user type search term in search field', async () => {
        const swSettingsCountryNewSnippetModalComponent = await wrapTestComponent('sw-settings-country-new-snippet-modal', {
            sync: true,
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const searchInputField = wrapper.find('.sw-settings-country-new-snippet-modal__input-field');
        let treesItem = wrapper.find('.tree-items .sw-tree-item__children');

        expect(treesItem.findAll('.sw-tree-item')).toHaveLength(2);

        await searchInputField.setValue('First');
        await searchInputField.trigger('input');

        const debouncedSearch = swSettingsCountryNewSnippetModalComponent.methods.debouncedSearch;
        await debouncedSearch.flush();

        treesItem = wrapper.find('.tree-items .sw-tree-item__children');

        let results = treesItem.findAll('.sw-tree-item');
        expect(results.at(0).find('.sw-tree-item__label').text()).toBe('First name');
        expect(results).toHaveLength(1);

        // no snippet match
        await searchInputField.setValue('xyz');
        await searchInputField.trigger('input');

        await debouncedSearch.flush();
        treesItem = wrapper.find('.tree-items .sw-tree-item__children');
        results = treesItem.findAll('.sw-tree-item');

        expect(results.at(0).find('.sw-tree-item__label').text()).toBe('First name');
        expect(results).toHaveLength(1);

        // no snippet
        await searchInputField.setValue('');
        await searchInputField.trigger('input');

        await debouncedSearch.flush();

        treesItem = wrapper.find('.tree-items .sw-tree-item__children');

        expect(treesItem.findAll('.sw-tree-item')).toHaveLength(2);
    });
});
