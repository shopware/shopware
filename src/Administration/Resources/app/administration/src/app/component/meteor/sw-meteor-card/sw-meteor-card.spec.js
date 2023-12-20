/**
 * @package admin
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
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
            },
            provide: {},
        },
        ...customConfig,
    });
}

describe('src/app/component/meteor/sw-meteor-card', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
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
        const wrapper = mount({
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
        }, {
            global: {
                stubs: {
                    'sw-meteor-card': await wrapTestComponent('sw-meteor-card'),
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                },
            },
        });

        await flushPromises();

        const tabItems = wrapper.findAll('.sw-tabs-item');
        expect(tabItems.at(0).text()).toBe('Tab 1');
        expect(tabItems.at(1).text()).toBe('Tab 2');
    });

    it('should render tabs and change content', async () => {
        const wrapper = mount({
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
        }, {
            global: {
                stubs: {
                    'sw-meteor-card': await wrapTestComponent('sw-meteor-card'),
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                },
            },
        });

        await flushPromises();

        const tabTwo = wrapper.findAll('.sw-tabs-item').at(1);

        let content = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(content.text()).toBe('Tab 1');

        await tabTwo.trigger('click');

        content = wrapper.find('.sw-meteor-card__content-wrapper');
        expect(content.text()).toBe('Tab 2');
    });
});
