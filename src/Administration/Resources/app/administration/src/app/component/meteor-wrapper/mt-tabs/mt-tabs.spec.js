/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { h } from 'vue';

async function createWrapper(options = {}) {
    const { props = {}, slots = {}, routerPush = jest.fn() } = options;

    return mount(await wrapTestComponent('mt-tabs', { sync: true }), {
        props: {
            items: [],
            positionIdentifier: 'jest-test-component',
            ...props,
        },
        slots,
        global: {
            stubs: {
                'sw-extension-component-section': {
                    name: 'sw-extension-component-section',
                    template: '<div class="sw-extension-component-section">{{ positionIdentifier }}</div>',
                    props: [
                        'positionIdentifier',
                    ],
                },
            },
            mocks: {
                $router: {
                    push: routerPush,
                },
            },
        },
    });
}

describe('src/app/component/meteor-wrapper/mt-tabs', () => {
    beforeEach(() => {
        // reset store
        Shopware.Store.get('tabs').tabItems = {};
    });

    it('should pass the items from the props to the final component', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
                { label: 'Tab 2', name: 'tab2' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('items')).toEqual([
            { label: 'Tab 1', name: 'tab1' },
            { label: 'Tab 2', name: 'tab2' },
        ]);
    });

    it('should use the legacy small layout by default', async () => {
        const wrapper = await createWrapper();

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('small')).toBe(true);
    });

    it('should allow consumers to opt out of the legacy small layout', async () => {
        const wrapper = await createWrapper({
            props: {
                small: false,
            },
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('small')).toBe(false);
    });

    it('should pass the merged items from the props and extension store to the final component', async () => {
        const wrapper = await createWrapper();

        // Set values in the extension store
        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
            { label: 'Tab 4', componentSectionId: 'tab4' },
        ];

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
                { label: 'Tab 2', name: 'tab2' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('items')).toEqual([
            { label: 'Tab 1', name: 'tab1' },
            { label: 'Tab 2', name: 'tab2' },
            { label: 'Tab 3', name: 'tab3', onClick: expect.any(Function) },
            { label: 'Tab 4', name: 'tab4', onClick: expect.any(Function) },
        ]);
    });

    it('should render the content slot and active extension component section', async () => {
        const routerPush = jest.fn();
        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        const wrapper = await createWrapper({
            props: {
                defaultItem: 'tab1',
                items: [
                    { label: 'Tab 1', name: 'tab1' },
                ],
            },
            slots: {
                content: ({ active }) => h('div', { class: 'tab-content' }, active),
            },
            routerPush,
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('items')).toEqual([
            { label: 'Tab 1', name: 'tab1' },
            { label: 'Tab 3', name: 'tab3' },
        ]);
        expect(wrapper.find('.mt-tabs__custom-content').exists()).toBe(true);
        expect(wrapper.get('.tab-content').text()).toBe('tab1');
        expect(wrapper.find('.sw-extension-component-section').exists()).toBe(false);

        await mtTabsOriginal.vm.$emit('new-item-active', 'tab3');

        expect(wrapper.get('.tab-content').text()).toBe('tab3');
        expect(wrapper.getComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe('tab3');
        expect(routerPush).not.toHaveBeenCalled();
        expect(wrapper.emitted('new-item-active')).toEqual([
            [
                'tab3',
            ],
        ]);
    });
});
