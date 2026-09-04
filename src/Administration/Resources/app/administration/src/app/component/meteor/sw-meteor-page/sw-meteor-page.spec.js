/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/meteor/sw-meteor-page';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

const pageTabsSlot = `
<sw-tabs-item :route="{ name: 'tab.one' }">
    Tab 1
</sw-tabs-item>

<sw-tabs-item :route="{ name: 'tab.two' }">
    Tab 2
</sw-tabs-item>

<sw-tabs-item :route="{ name: 'tab.three' }">
    Tab 3
</sw-tabs-item>
`;

const pageTabsSlotWithTitle = `
<sw-tabs-item
    name="tab.one"
    :route="{ name: 'tab.one' }"
    title="Tooltip text"
>
    Visible tab text
</sw-tabs-item>
`;

async function createWrapper(slotsData = {}, { routeName = undefined } = {}) {
    return mount(await wrapTestComponent('sw-meteor-page', { sync: true }), {
        global: {
            stubs: {
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-help-center-v2': true,
                'sw-meteor-page-context': true,
                'sw-meteor-navigation': {
                    props: ['fromLink'],
                    template: '<div class="sw-meteor-navigation"></div>',
                },
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'router-link': {
                    template: '<div class="router-link"><slot></slot></div>',
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: '',
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
                },
                'sw-extension-component-section': true,
                'sw-app-topbar-button': true,
                'sw-app-topbar-sidebar': true,
            },
            mocks: {
                $route: {
                    name: routeName,
                    meta: {
                        $module: {
                            icon: 'regular-plug',
                            title: 'sw.example.title',
                            color: 'var(--color-module-brand-default)',
                        },
                    },
                },
                $router: {
                    push: jest.fn(),
                    resolve() {
                        return {
                            matched: [],
                        };
                    },
                },
            },
        },
        slots: slotsData,
        props: {
            fromLink: {
                name: 'path.to.from.link',
            },
        },
    });
}

describe('src/app/component/meteor/sw-meteor-page', () => {
    beforeAll(() => {
        /**
         * Warning happens due to the non-reactive programmatic usage
         * of slots in the deprecated sw-tabs component
         */
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes(
                    'Slot "page-tabs" invoked outside of the render function: this will not track dependencies used in the slot. Invoke the slot function inside the render function instead',
                );
            },
        });
    });

    it('should be in full width', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            fullWidth: true,
        });

        expect(wrapper.classes()).toContain('sw-meteor-page--full-width');
    });

    it('should hide the icon', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            hideIcon: true,
        });

        const iconComponent = wrapper.find('.mt-icon');
        expect(iconComponent.exists()).toBe(false);
    });

    it('should hide the smart bar', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-meteor-page__smart-bar').exists()).toBe(true);

        await wrapper.setProps({
            hideSmartBar: true,
        });

        expect(wrapper.classes()).toContain('sw-meteor-page--hide-smart-bar');
        expect(wrapper.find('.sw-meteor-page__smart-bar').exists()).toBe(false);
    });

    it('should render the module icon when slot "smart-bar-icon" is not filled', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const iconComponent = wrapper.findComponent('.mt-icon');
        expect(iconComponent.vm.name).toContain('regular-plug');
        expect(iconComponent.vm.color).toBe('var(--color-icon-primary-default)');
    });

    [
        'search-bar',
        'smart-bar-back',
        'smart-bar-icon',
        'smart-bar-header',
        'smart-bar-header-meta',
        'smart-bar-description',
        'smart-bar-actions',
        'smart-bar-context-buttons',
    ].forEach((slotName) => {
        it(`should render the content of the slot "${slotName}"`, async () => {
            const wrapper = await createWrapper({
                [slotName]: '<div id="test-slot">This slot works</div>',
            });
            await flushPromises();

            const testSlot = wrapper.find('#test-slot');

            expect(testSlot.exists()).toBe(true);
            expect(testSlot.text()).toBe('This slot works');
        });
    });

    it('should render the meteor navigation component when the slot "smart-bar-back" is not used', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const navigationComponent = wrapper.findComponent('.sw-meteor-navigation');

        expect(navigationComponent.exists()).toBe(true);

        expect(navigationComponent.props('fromLink')).toEqual({
            name: 'path.to.from.link',
        });
    });

    it('should not render the meteor navigation component when the slot "smart-bar-back" is not used', async () => {
        const wrapper = await createWrapper({
            'smart-bar-back': '<div id="test-slot">This slot works</div>',
        });
        await flushPromises();

        const navigationComponent = wrapper.find('sw-meteor-navigation-stub');
        expect(navigationComponent.exists()).toBe(false);
    });

    it('should render the title of the page when slot "smart-bar-header" is not filled', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const title = wrapper.find('.sw-meteor-page__smart-bar-title');

        expect(title.exists()).toBe(true);
        expect(title.text()).toBe('sw.example.title');
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should render the deprecated tabs when slot is filled', async () => {
        const wrapper = await createWrapper({
            'page-tabs': pageTabsSlot,
        });

        await flushPromises();

        const tabsContent = wrapper.find('.sw-tabs__content');
        expect(tabsContent.exists()).toBe(true);

        const routerLinksStubs = wrapper.findAll('.router-link');
        expect(routerLinksStubs).toHaveLength(3);

        expect(routerLinksStubs.at(0).text()).toBe('Tab 1');
        expect(routerLinksStubs.at(1).text()).toBe('Tab 2');
        expect(routerLinksStubs.at(2).text()).toBe('Tab 3');

        expect(wrapper.find('.mt-tabs').exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs when slot is filled', async () => {
        const wrapper = await createWrapper(
            {
                'page-tabs': pageTabsSlot,
            },
            {
                routeName: 'tab.two',
            },
        );

        await flushPromises();

        const tabs = wrapper.findComponent({ name: 'mt-tabs' });
        expect(tabs.exists()).toBe(true);
        expect(tabs.props('positionIdentifier')).toBe('sw-meteor-page');
        expect(tabs.props('defaultItem')).toBe('tab.two');

        const items = tabs.props('items');
        expect(items).toHaveLength(3);
        expect(items.map(({ label, name }) => ({ label, name }))).toEqual([
            {
                label: 'Tab 1',
                name: 'tab.one',
            },
            {
                label: 'Tab 2',
                name: 'tab.two',
            },
            {
                label: 'Tab 3',
                name: 'tab.three',
            },
        ]);

        items[1].onClick();
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'tab.two' });

        await tabs.vm.$emit('new-item-active', 'tab.three');
        expect(wrapper.emitted('new-item-active')).toEqual([
            [
                'tab.three',
            ],
        ]);

        expect(wrapper.find('.sw-tabs__content').exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should prefer the visible tab text over the title attribute for meteor tab labels',
        async () => {
            const wrapper = await createWrapper({
                'page-tabs': pageTabsSlotWithTitle,
            });

            await flushPromises();

            const tabs = wrapper.getComponent({ name: 'mt-tabs' });

            expect(tabs.props('items').map(({ label, name }) => ({ label, name }))).toEqual([
                {
                    label: 'Visible tab text',
                    name: 'tab.one',
                },
            ]);
        },
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should not render the tabs when slot is empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-tabs__content').exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should not render the tabs when slot is empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render the content', async () => {
        const wrapper = await createWrapper({
            default: '<p>Lorem Ipsum</p>',
        });
        await flushPromises();

        const pageContent = wrapper.find('.sw-meteor-page__content');
        expect(pageContent.text()).toBe('Lorem Ipsum');
    });

    it('should contain sw-help-center-v2 and sw-notification-center', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const globalActions = wrapper.get('.sw-meteor-page__head-area-global-actions');

        expect(globalActions.get('sw-help-center-v2-stub').exists()).toBe(true);
        expect(globalActions.get('sw-notification-center-stub').exists()).toBe(true);
    });
});
