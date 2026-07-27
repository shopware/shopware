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

    it('should not use the small layout by default', async () => {
        const wrapper = await createWrapper();

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('small')).toBe(false);
    });

    it('should allow consumers to opt into the small layout', async () => {
        const wrapper = await createWrapper({
            props: {
                small: true,
            },
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('small')).toBe(true);
    });

    it('should pass the merged items from the props and extension store to the final component', async () => {
        const wrapper = await createWrapper();

        // Set values in the extension store
        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
            { label: 'Tab 4', componentSectionId: 'tab4' },
        ];

        await wrapper.setProps({
            useRoutesForExtensions: true,
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

    it('should not pass extension tabs whose visible flag is false', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3', visible: true },
            { label: 'Tab 4', componentSectionId: 'tab4', visible: false },
            { label: 'Tab 5', componentSectionId: 'tab5' },
        ];

        await wrapper.setProps({
            useRoutesForExtensions: true,
            items: [{ label: 'Tab 1', name: 'tab1' }],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('items')).toEqual([
            { label: 'Tab 1', name: 'tab1' },
            { label: 'Tab 3', name: 'tab3', onClick: expect.any(Function) },
            { label: 'Tab 5', name: 'tab5', onClick: expect.any(Function) },
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

    it('should render routed extension tabs only through the generated route', async () => {
        const routerPush = jest.fn();
        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        const wrapper = await createWrapper({
            props: {
                defaultItem: 'tab1',
                useRoutesForExtensions: true,
                items: [
                    { label: 'Tab 1', name: 'tab1' },
                ],
            },
            routerPush,
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });

        expect(wrapper.find('.mt-tabs__custom-content').exists()).toBe(false);

        await mtTabsOriginal.vm.$emit('new-item-active', 'tab3');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.mt-tabs__custom-content').exists()).toBe(false);
        expect(wrapper.find('.sw-extension-component-section').exists()).toBe(false);

        const extensionTab = mtTabsOriginal.props('items')[1];
        extensionTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            path: 'tab3',
        });
    });

    it('should keep generated extension routes active with the component section id', async () => {
        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        const wrapper = await createWrapper({
            props: {
                defaultItem: 'sw.test.index.tab3',
                useRoutesForExtensions: true,
                items: [
                    { label: 'Tab 1', name: 'sw.test.index' },
                ],
            },
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });

        expect(mtTabsOriginal.props('defaultItem')).toBe('tab3');
        expect(wrapper.find('.mt-tabs__custom-content').exists()).toBe(false);
        expect(wrapper.find('.sw-extension-component-section').exists()).toBe(false);
    });

    it('should infer route mode for extension tabs when the surface items are route-backed', async () => {
        const routerPush = jest.fn();
        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        const wrapper = await createWrapper({
            props: {
                defaultItem: 'tab1',
                items: [
                    { label: 'Tab 1', name: 'tab1', onClick: jest.fn() },
                ],
            },
            routerPush,
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        const extensionTab = mtTabsOriginal.props('items')[1];

        expect(extensionTab.onClick).toEqual(expect.any(Function));
        expect(wrapper.find('.sw-extension-component-section').exists()).toBe(false);

        extensionTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ path: 'tab3' });
    });

    it('should infer inline mode for extension tabs when the surface items are not route-backed', async () => {
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
            routerPush,
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });

        expect(mtTabsOriginal.props('items')[1].onClick).toBeUndefined();

        await mtTabsOriginal.vm.$emit('new-item-active', 'tab3');

        expect(wrapper.find('.mt-tabs__custom-content').exists()).toBe(true);
        expect(wrapper.getComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe('tab3');
        expect(routerPush).not.toHaveBeenCalled();
    });

    it('should let an explicit use-routes-for-extensions="false" override route-backed items', async () => {
        const routerPush = jest.fn();
        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        const wrapper = await createWrapper({
            props: {
                defaultItem: 'tab1',
                useRoutesForExtensions: false,
                items: [
                    { label: 'Tab 1', name: 'tab1', onClick: jest.fn() },
                ],
            },
            routerPush,
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });

        expect(mtTabsOriginal.props('items')[1].onClick).toBeUndefined();

        await mtTabsOriginal.vm.$emit('new-item-active', 'tab3');

        expect(wrapper.getComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe('tab3');
        expect(routerPush).not.toHaveBeenCalled();
    });
});
