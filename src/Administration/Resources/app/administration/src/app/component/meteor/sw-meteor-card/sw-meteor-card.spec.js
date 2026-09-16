/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/meteor/sw-meteor-card';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

function createFeatureMock(featureActive = false) {
    return {
        isActive: (flag) => flag === 'v6.8.0.0' && featureActive,
    };
}

function createMtTabsStub() {
    return {
        name: 'mt-tabs',
        emits: ['new-item-active'],
        props: {
            defaultItem: {
                type: String,
                required: false,
                default: undefined,
            },
            items: {
                type: Array,
                required: true,
            },
            positionIdentifier: {
                type: String,
                required: true,
            },
        },
        template: '<div class="mt-tabs"></div>',
    };
}

async function createWrapper(customConfig = {}, { featureActive = false } = {}) {
    return mount(await wrapTestComponent('sw-meteor-card', { sync: true }), {
        props: {},
        global: {
            stubs: {
                'sw-loader': true,
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'mt-tabs': createMtTabsStub(),
                'sw-extension-component-section': true,
                'router-link': true,
            },
            provide: {
                feature: createFeatureMock(featureActive),
            },
        },
        ...customConfig,
    });
}

async function createMeteorCardWithTabs() {
    return mount(
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
                    'mt-tabs': createMtTabsStub(),
                    'sw-extension-component-section': true,
                    'router-link': true,
                },
            },
        },
    );
}

describe('src/app/component/meteor/sw-meteor-card', () => {
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

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should render the tabs', async () => {
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
                        'mt-tabs': createMtTabsStub(),
                        'sw-extension-component-section': true,
                        'router-link': true,
                    },
                    provide: {
                        feature: createFeatureMock(),
                    },
                },
            },
        );

        await flushPromises();

        const tabItems = wrapper.findAll('.sw-tabs-item');
        expect(tabItems.at(0).text()).toBe('Tab 1');
        expect(tabItems.at(1).text()).toBe('Tab 2');
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should render deprecated tabs and change content', async () => {
        const wrapper = await createMeteorCardWithTabs();
        await flushPromises();

        const tabTwo = wrapper.findAll('.sw-tabs-item').at(1);

        let content = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(content.text()).toBe('Tab 1');

        await tabTwo.trigger('click');

        content = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(content.text()).toBe('Tab 2');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs and change content', async () => {
        const wrapper = await createMeteorCardWithTabs();
        await flushPromises();

        const tabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(tabs.exists()).toBe(true);
        expect(tabs.props('positionIdentifier')).toBe('sw-meteor-card');
        expect(tabs.props('defaultItem')).toBe('tab1');
        expect(tabs.props('items')).toEqual([
            {
                label: 'Tab 1',
                name: 'tab1',
            },
            {
                label: 'Tab 2',
                name: 'tab2',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);

        let content = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(content.text()).toBe('Tab 1');

        await tabs.vm.$emit('new-item-active', 'tab2');
        await flushPromises();

        content = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(content.text()).toBe('Tab 2');
        expect(tabs.props('defaultItem')).toBe('tab2');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should prefer the visible tab text over the title attribute for meteor tab labels',
        async () => {
            const wrapper = mount(
                {
                    template: `
<sw-meteor-card defaultTab="tab1">
    <template #tabs="{ activeTab }">
        <sw-tabs-item
            name="tab1"
            title="Tooltip text"
            :activeTab="activeTab"
        >
            Visible tab text
        </sw-tabs-item>
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
                            'mt-tabs': createMtTabsStub(),
                            'sw-extension-component-section': true,
                            'router-link': true,
                        },
                    },
                },
            );

            await flushPromises();

            const tabs = wrapper.getComponent({ name: 'mt-tabs' });

            expect(tabs.props('items')).toEqual([
                {
                    label: 'Visible tab text',
                    name: 'tab1',
                },
            ]);
        },
    );
});
