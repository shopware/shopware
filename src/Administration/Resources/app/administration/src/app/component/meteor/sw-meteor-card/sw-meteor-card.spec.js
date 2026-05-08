/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/meteor/sw-meteor-card';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

async function createWrapper(customConfig = {}) {
    return mount(await wrapTestComponent('sw-meteor-card', { sync: true }), {
        props: {},
        global: {
            stubs: {
                'sw-loader': true,
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: ['defaultItem', 'items', 'positionIdentifier', 'routeExtensionTabs'],
                    template: '<div class="mt-tabs" @click="$emit(\'new-item-active\', { name: \'extension-tab\' })"></div>',
                },
                'sw-extension-component-section': {
                    name: 'sw-extension-component-section',
                    props: ['positionIdentifier'],
                    template: '<div class="sw-extension-component-section"></div>',
                },
                'router-link': true,
            },
            provide: {},
        },
        ...customConfig,
    });
}

describe('src/app/component/meteor/sw-meteor-card', () => {
    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes(
                    'Slot "tabs" invoked outside of the render function: this will not track dependencies used in the slot. Invoke the slot function inside the render function instead',
                );
            },
        });
    });

    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should render the content of the default slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>I am in the default slot</p>',
            },
        });

        const contentWrapper = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(contentWrapper.text()).toBe('I am in the default slot');
    });

    it('should render the content of the default scoped slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>I am in the default slot</p>',
            },
        });

        const contentWrapper = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(contentWrapper.text()).toBe('I am in the default slot');
    });

    it('should render the title as prop', async () => {
        const wrapper = await createWrapper({
            props: {
                title: 'Welcome to Shopware',
            },
        });

        const title = wrapper.find('.sw-meteor-card__title');
        expect(title.text()).toBe('Welcome to Shopware');
    });

    it('should render as hero card', async () => {
        const wrapper = await createWrapper({
            props: {
                hero: true,
            },
        });

        expect(wrapper.classes()).toContain('sw-meteor-card--hero');
    });

    it('should render a loading indicator', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>Lorem Ipsum</p>',
            },
        });

        let loader = wrapper.find('sw-loader-stub');
        expect(loader.exists()).toBe(false);

        await wrapper.setProps({ isLoading: true });

        loader = wrapper.find('sw-loader-stub');
        expect(loader.exists()).toBe(true);
        expect(loader.isVisible()).toBe(true);
    });

    it('should render a large card', async () => {
        const wrapper = await createWrapper({
            props: {
                large: true,
            },
        });

        expect(wrapper.classes()).toContain('sw-meteor-card--large');
    });

    it('should render a something in the toolbar slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                toolbar: '<p>I am in the toolbar slot</p>',
            },
        });

        const toolbarSlot = wrapper.find('.sw-meteor-card__toolbar');
        expect(toolbarSlot.text()).toBe('I am in the toolbar slot');
    });

    it('should render a something in the footer slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                footer: '<p>I am in the footer slot</p>',
            },
        });

        const footerSlot = wrapper.find('.sw-meteor-card__footer');
        expect(footerSlot.text()).toBe('I am in the footer slot');
    });

    it('should render a something in the grid slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                grid: '<p>I am in the grid slot</p>',
            },
        });

        const contentWrapper = wrapper.find('.sw-meteor-card__content');
        expect(contentWrapper.text()).toBe('I am in the grid slot');
    });

    it('should render a something in the action slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                action: '<p>I am in the action slot</p>',
            },
        });

        const actionsSlot = wrapper.find('.sw-meteor-card__header-action');
        expect(actionsSlot.text()).toBe('I am in the action slot');
    });

    it('should render the tabs', async () => {
        const wrapper = mount(
            {
                template: `
<sw-meteor-card defaultTab="tab1">

    <template #tabs="{ activeTab }">
        <sw-tabs-item name="tab1" :activeTab="activeTab">Tab 1</sw-tabs-item>
        <sw-tabs-item name="tab2" :activeTab="activeTab">Tab 2</sw-tabs-item>
    </template>

    <template #content="{ activeTab }">
        <p v-if="activeTab === 'tab1'">Tab 1</p>
        <p v-if="activeTab === 'tab2'">Tab 2</p>
    </template>

</sw-meteor-card>
            `,
            },
            {
                global: {
                    stubs: {
                        'sw-meteor-card': await wrapTestComponent('sw-meteor-card'),
                        'sw-tabs': await wrapTestComponent('sw-tabs'),
                        'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                        'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                        'sw-loader': true,
                        'mt-tabs': true,
                        'sw-extension-component-section': true,
                        'router-link': true,
                    },
                },
            },
        );

        await flushPromises();

        const tabItems = wrapper.findAll('.sw-tabs-item');
        expect(tabItems.at(0).text()).toBe('Tab 1');
        expect(tabItems.at(1).text()).toBe('Tab 2');
    });

    it('should keep rendering legacy tab slots when the major migration is inactive', async () => {
        const wrapper = await createWrapper({
            slots: {
                tabs: `
<sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
<sw-tabs-item name="tab2">Tab 2</sw-tabs-item>
                `,
            },
        });

        await flushPromises();

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
        expect(wrapper.findAll('.sw-tabs-item')).toHaveLength(2);
    });

    it('should render mt-tabs from card tab items when the major migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const cardTabs = [
            { label: 'Tab 1', name: 'tab1' },
            { label: 'Tab 2', name: 'tab2' },
        ];

        const wrapper = await createWrapper({
            props: {
                defaultTab: 'tab1',
                cardTabs,
            },
        });

        await flushPromises();

        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });
        expect(mtTabs.exists()).toBe(true);
        expect(mtTabs.props('items')).toEqual(cardTabs);
        expect(mtTabs.props('defaultItem')).toBe('tab1');
        expect(mtTabs.props('positionIdentifier')).toBe('sw-meteor-card');
        expect(mtTabs.props('routeExtensionTabs')).toBe(false);
        expect(wrapper.find('.sw-tabs__content').exists()).toBe(false);
    });

    it('should render mt-tabs only when the major migration is active with card tab items and legacy tab slots', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper({
            props: {
                defaultTab: 'tab1',
                cardTabs: [{ label: 'Tab 1', name: 'tab1' }],
            },
            slots: {
                tabs: '<sw-tabs-item name="legacy-tab">Legacy tab</sw-tabs-item>',
            },
        });

        await flushPromises();

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(true);
        expect(wrapper.find('.sw-tabs__content').exists()).toBe(false);
        expect(wrapper.find('.sw-tabs-item').exists()).toBe(false);
    });

    it('should keep rendering legacy tab slots when the major migration is active without card tab items', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper({
            slots: {
                tabs: '<sw-tabs-item name="tab1">Tab 1</sw-tabs-item>',
            },
        });

        await flushPromises();

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
        expect(wrapper.find('.sw-tabs__content').exists()).toBe(true);
        expect(wrapper.find('.sw-tabs-item').text()).toBe('Tab 1');
    });

    it('should normalize active mt-tabs items when changing the active tab', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper({
            props: {
                defaultTab: 'tab1',
                cardTabs: [
                    { label: 'Tab 1', name: 'tab1' },
                    { label: 'Tab 2', name: 'tab2' },
                ],
            },
        });

        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('tab1');

        wrapper.vm.setActiveTab({ name: 'tab2' });

        expect(wrapper.vm.activeTab).toBe('tab2');
    });

    it('should render extension component section when an extension mt-tabs item becomes active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper({
            props: {
                defaultTab: 'tab1',
                cardTabs: [{ label: 'Tab 1', name: 'tab1' }],
            },
        });

        await flushPromises();

        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).exists()).toBe(false);

        await wrapper.findComponent({ name: 'mt-tabs' }).trigger('click');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('extension-tab');
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );
    });

    it('should render tabs and change content', async () => {
        const wrapper = mount(
            {
                template: `
<sw-meteor-card defaultTab="tab1">

    <template #tabs="{ activeTab }">
        <sw-tabs-item name="tab1" :activeTab="activeTab">Tab 1</sw-tabs-item>
        <sw-tabs-item name="tab2" :activeTab="activeTab">Tab 2</sw-tabs-item>
    </template>

    <template #default="{ activeTab }">
        <p v-if="activeTab === 'tab1'">Tab 1</p>
        <p v-if="activeTab === 'tab2'">Tab 2</p>
    </template>

</sw-meteor-card>
            `,
            },
            {
                global: {
                    stubs: {
                        'sw-meteor-card': await wrapTestComponent('sw-meteor-card'),
                        'sw-tabs': await wrapTestComponent('sw-tabs'),
                        'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                        'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                        'sw-loader': true,
                        'mt-tabs': true,
                        'sw-extension-component-section': true,
                        'router-link': true,
                    },
                },
            },
        );

        await flushPromises();

        const tabTwo = wrapper.findAll('.sw-tabs-item').at(1);

        let content = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(content.text()).toBe('Tab 1');

        await tabTwo.trigger('click');

        content = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(content.text()).toBe('Tab 2');
    });
});
