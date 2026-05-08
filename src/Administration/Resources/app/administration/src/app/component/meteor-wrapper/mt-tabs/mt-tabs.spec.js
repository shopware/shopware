/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = {}, attrs = {}) {
    return mount(await wrapTestComponent('mt-tabs', { sync: true }), {
        attrs,
        global: {
            stubs: {
                'mt-tabs-original': {
                    name: 'mt-tabs-original',
                    props: ['items'],
                    emits: ['new-item-active'],
                    template: '<div />',
                },
            },
        },
        props: {
            items: [],
            positionIdentifier: 'jest-test-component',
            ...props,
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

    it('should keep core items on the wrapper and pass them to the final component', async () => {
        const items = [{ label: 'General', name: 'general' }];

        const wrapper = await createWrapper({
            positionIdentifier: 'example-tabs',
            items,
        });

        expect(wrapper.props('items')).toEqual([{ label: 'General', name: 'general' }]);
        expect(wrapper.findComponent({ name: 'mt-tabs-original' }).props('items')).toEqual([
            { label: 'General', name: 'general' },
        ]);
    });

    it('should pass core items before extension items to the final component', async () => {
        Shopware.Store.get('tabs').tabItems['example-tabs'] = [
            { label: 'Extension', componentSectionId: 'extension' },
        ];

        const wrapper = await createWrapper({
            positionIdentifier: 'example-tabs',
            items: [{ label: 'General', name: 'general' }],
        });

        expect(wrapper.findComponent({ name: 'mt-tabs-original' }).props('items')).toEqual([
            { label: 'General', name: 'general' },
            { label: 'Extension', name: 'extension', onClick: expect.any(Function) },
        ]);
    });

    it('should activate extension items without route navigation when route extension tabs are disabled', async () => {
        const routerPush = jest.fn();

        Shopware.Store.get('tabs').tabItems['example-tabs'] = [
            { label: 'Extension', componentSectionId: 'extension' },
        ];

        const wrapper = await mount(await wrapTestComponent('mt-tabs', { sync: true }), {
            global: {
                mocks: {
                    $router: {
                        push: routerPush,
                    },
                },
                stubs: {
                    'mt-tabs-original': {
                        name: 'mt-tabs-original',
                        props: ['items'],
                        emits: ['new-item-active'],
                        template: '<div />',
                    },
                },
            },
            props: {
                items: [{ label: 'General', name: 'general' }],
                positionIdentifier: 'example-tabs',
                routeExtensionTabs: false,
            },
        });

        const extensionItem = wrapper.findComponent({ name: 'mt-tabs-original' }).props('items').at(1);
        extensionItem.onClick();

        expect(routerPush).not.toHaveBeenCalled();
        expect(wrapper.emitted('new-item-active')).toEqual([[{ label: 'Extension', name: 'extension' }]]);
    });

    it('should not emit duplicate extension events when the original component already emitted the active item', async () => {
        const routerPush = jest.fn();

        Shopware.Store.get('tabs').tabItems['example-tabs'] = [
            { label: 'Extension', componentSectionId: 'extension' },
        ];

        const wrapper = await mount(await wrapTestComponent('mt-tabs', { sync: true }), {
            global: {
                mocks: {
                    $router: {
                        push: routerPush,
                    },
                },
                stubs: {
                    'mt-tabs-original': {
                        name: 'mt-tabs-original',
                        props: ['items'],
                        emits: ['new-item-active'],
                        template: '<div />',
                    },
                },
            },
            props: {
                items: [{ label: 'General', name: 'general' }],
                positionIdentifier: 'example-tabs',
                routeExtensionTabs: false,
            },
        });

        const extensionItem = wrapper.findComponent({ name: 'mt-tabs-original' }).props('items').at(1);

        wrapper.findComponent({ name: 'mt-tabs-original' }).vm.$emit('new-item-active', {
            label: 'Extension',
            name: 'extension',
        });
        extensionItem.onClick();

        expect(routerPush).not.toHaveBeenCalled();
        expect(wrapper.emitted('new-item-active')).toEqual([[{ label: 'Extension', name: 'extension' }]]);
    });

    it('should not emit duplicate extension events when the original component emitted the active item name', async () => {
        const routerPush = jest.fn();

        Shopware.Store.get('tabs').tabItems['example-tabs'] = [
            { label: 'Extension', componentSectionId: 'extension' },
        ];

        const wrapper = await mount(await wrapTestComponent('mt-tabs', { sync: true }), {
            global: {
                mocks: {
                    $router: {
                        push: routerPush,
                    },
                },
                stubs: {
                    'mt-tabs-original': {
                        name: 'mt-tabs-original',
                        props: ['items'],
                        emits: ['new-item-active'],
                        template: '<div />',
                    },
                },
            },
            props: {
                items: [{ label: 'General', name: 'general' }],
                positionIdentifier: 'example-tabs',
                routeExtensionTabs: false,
            },
        });

        const extensionItem = wrapper.findComponent({ name: 'mt-tabs-original' }).props('items').at(1);

        wrapper.findComponent({ name: 'mt-tabs-original' }).vm.$emit('new-item-active', 'extension');
        extensionItem.onClick();

        expect(routerPush).not.toHaveBeenCalled();
        expect(wrapper.emitted('new-item-active')).toEqual([['extension']]);
    });

    it('should forward new item active events from the final component', async () => {
        const wrapper = await createWrapper({
            positionIdentifier: 'example-tabs',
            items: [{ label: 'General', name: 'general' }],
        });

        wrapper.findComponent({ name: 'mt-tabs-original' }).vm.$emit('new-item-active', {
            label: 'General',
            name: 'general',
        });

        expect(wrapper.emitted('new-item-active')).toEqual([
            [{ label: 'General', name: 'general' }],
        ]);
    });

    it('should call parent new item active listeners once when forwarding the event', async () => {
        const onNewItemActive = jest.fn();
        const wrapper = await createWrapper(
            {
                positionIdentifier: 'example-tabs',
                items: [{ label: 'General', name: 'general' }],
            },
            {
                onNewItemActive,
            },
        );

        wrapper.findComponent({ name: 'mt-tabs-original' }).vm.$emit('new-item-active', {
            label: 'General',
            name: 'general',
        });

        expect(onNewItemActive).toHaveBeenCalledTimes(1);
        expect(onNewItemActive).toHaveBeenCalledWith({
            label: 'General',
            name: 'general',
        });
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
});
