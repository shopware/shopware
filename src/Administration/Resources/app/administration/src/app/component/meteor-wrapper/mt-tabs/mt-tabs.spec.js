/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = {}, route = { fullPath: '' }) {
    return mount(await wrapTestComponent('mt-tabs', { sync: true }), {
        props: {
            items: [],
            positionIdentifier: 'jest-test-component',
            ...props,
        },
        global: {
            mocks: {
                $router: {
                    push: jest.fn(),
                },
                $route: route,
            },
            stubs: {
                'sw-extension-component-section': {
                    name: 'sw-extension-component-section',
                    props: ['positionIdentifier'],
                    template: '<div class="sw-extension-component-section-stub"></div>',
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

    it('should not require a position identifier', async () => {
        const wrapper = await createWrapper({
            positionIdentifier: undefined,
            items: [
                { label: 'Tab 1', name: 'tab1' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('items')).toEqual([
            { label: 'Tab 1', name: 'tab1' },
        ]);
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
            { label: 'Tab 3', name: 'tab3' },
            { label: 'Tab 4', name: 'tab4' },
        ]);
    });

    it('should forward new active item events for regular items', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        await mtTabsOriginal.vm.$emit('new-item-active', 'tab1');

        expect(wrapper.emitted('new-item-active')).toEqual([['tab1']]);
    });

    it('should render extension content for static extension tabs', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        await mtTabsOriginal.vm.$emit('new-item-active', 'tab3');

        const extensionSection = wrapper.getComponent('.sw-extension-component-section-stub');
        expect(extensionSection.props('positionIdentifier')).toBe('tab3');
        expect(wrapper.emitted('new-item-active')).toBeUndefined();
        expect(wrapper.emitted('extension-item-active')).toEqual([['tab3']]);
    });

    it('should not render extension content when static extension content rendering is disabled', async () => {
        const wrapper = await createWrapper({ renderExtensionContent: false });

        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        await mtTabsOriginal.vm.$emit('new-item-active', 'tab3');

        expect(wrapper.findComponent('.sw-extension-component-section-stub').exists()).toBe(false);
        expect(wrapper.emitted('extension-item-active')).toEqual([['tab3']]);
    });

    it('should navigate to the generated extension route path on extension item click', async () => {
        const wrapper = await createWrapper(
            {
                routeTabs: true,
            },
            {
                fullPath: '/admin/example',
                query: {
                    edit: true,
                },
            },
        );

        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        mtTabsOriginal.props('items')[1].onClick();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            path: 'tab3',
            query: {
                edit: true,
            },
        });
    });

    it('should use the generated extension route path as active route item', async () => {
        Shopware.Store.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
        ];

        const wrapper = await createWrapper(
            {
                defaultItem: 'tab1',
                routeTabs: true,
                items: [
                    { label: 'Tab 1', name: 'tab1' },
                ],
            },
            { fullPath: '/admin/example/tab3' },
        );

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('defaultItem')).toBe('tab3');
    });
});
