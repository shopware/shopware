import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-new-snippet-modal/index';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-modal';
import 'src/app/component/tree/sw-tree';
import 'src/app/component/tree/sw-tree-item';
import 'src/app/component/tree/sw-tree-input-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/utils/sw-vnode-renderer';
import 'src/app/component/form/field-base/sw-contextual-field';

const swSettingsCountryNewSnippetModalComponent = Shopware.Component.build('sw-settings-country-new-snippet-modal');

function createWrapper(customPropsData = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(swSettingsCountryNewSnippetModalComponent, {
        localVue,

        mocks: {
            $tc: key => key,
            $route: {
                params: {
                    id: 'id'
                }
            },
            $device: {
                getSystemKey: () => {},
                onResize: () => {}
            }
        },

        propsData: {
            selections: [
                {
                    id: 'plain/-',
                    name: '-',
                    parentId: 'plain'
                },
                {
                    id: 'plain/,',
                    name: ',',
                    parentId: 'plain'
                },
                {
                    id: 'address/country_state',
                    name: 'Country State',
                    parentId: null
                },
                {
                    id: 'address/salutation',
                    name: 'Salutation',
                    parentId: null,
                },
                {
                    id: 'address/country',
                    name: 'Country',
                    parentId: null
                },
                {
                    id: 'address/zipcode',
                    name: 'Zip code',
                    parentId: null
                },
                {
                    id: 'address/first_name',
                    name: 'First name',
                    parentId: null
                }
            ],
            currentPosition: 0,
            addressFormat: [
                [
                    { value: 'address/company', type: 'snippet' },
                    { value: '-', type: 'plain' },
                    { value: 'address/department', type: 'snippet' },
                ]
            ],
            ...customPropsData
        },

        provide: {
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            }
        },

        stubs: {
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-label': Shopware.Component.build('sw-label'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': true,
            'sw-tree': Shopware.Component.build('sw-tree'),
            'sw-tree-item': Shopware.Component.build('sw-tree-item'),
            'sw-tree-input-field': Shopware.Component.build('sw-tree-input-field'),
            'sw-confirm-field': true,
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-context-menu-item': {
                template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`
            },
            'sw-vnode-renderer': Shopware.Component.build('sw-vnode-renderer'),
            'sw-skeleton': true,
        }
    });
}

describe('src/module/sw-settings-country/component/sw-settings-country-new-snippet-modal', () => {
    beforeAll(async () => {
        Shopware.Utils.debounce = function debounce(fn) {
            return function execFunction(...args) {
                fn.apply(this, args);
            };
        };
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to remove the snippet', async () => {
        const wrapper = await createWrapper();

        const button = wrapper.find('.sw-select-selection-list__item-holder--0 > span');

        await button.find('.sw-label__dismiss').trigger('click');

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0]).toEqual([
            0,
            [
                { value: '-', type: 'plain' },
                { value: 'address/department', type: 'snippet' }
            ]
        ]);
    });

    it('should be able to add new snippet', async () => {
        const wrapper = await createWrapper();

        const treeItemChildren = wrapper.find('.tree-items .sw-tree-item__children');

        await treeItemChildren.find('.sw-tree-item__element .sw-button').trigger('click');

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0]).toEqual([
            0,
            [
                { value: 'address/company', type: 'snippet' },
                { value: '-', type: 'plain' },
                { value: 'address/department', type: 'snippet' },
                { type: 'plain', value: '-' }
            ]
        ]);
    });

    it('should be able to reorder data when user type search term in search field', async () => {
        const wrapper = await createWrapper();

        const searchInputField = wrapper.find('.sw-settings-country-new-snippet-modal__input-field');
        let treesItem = wrapper.find('.tree-items .sw-tree-item__children');

        expect(treesItem.findAll('.sw-tree-item').length).toBe(2);

        await searchInputField.setValue('First');
        await searchInputField.trigger('input');

        const debouncedSearch = swSettingsCountryNewSnippetModalComponent.methods.debouncedSearch;
        await debouncedSearch.flush();

        treesItem = wrapper.find('.tree-items .sw-tree-item__children');

        let results = treesItem.findAll('.sw-tree-item');
        expect(results.at(0).find('.sw-tree-item__label').text()).toEqual('First name');
        expect(results.length).toBe(1);

        // no snippet match
        await searchInputField.setValue('xyz');
        await searchInputField.trigger('input');

        await debouncedSearch.flush();
        treesItem = wrapper.find('.tree-items .sw-tree-item__children');
        results = treesItem.findAll('.sw-tree-item');

        expect(results.at(0).find('.sw-tree-item__label').text()).toEqual('First name');
        expect(results.length).toBe(1);

        // no snippet
        await searchInputField.setValue('');
        await searchInputField.trigger('input');

        await debouncedSearch.flush();

        treesItem = wrapper.find('.tree-items .sw-tree-item__children');

        expect(treesItem.findAll('.sw-tree-item').length).toBe(2);
    });
});
