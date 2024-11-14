/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/meteor/sw-meteor-page';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

async function createWrapper(slotsData = {}) {
    return mount(await wrapTestComponent('sw-meteor-page', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': true,
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
                'mt-tabs': true,
                'sw-extension-component-section': true,
                'sw-app-topbar-button': true,
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'default-object-plug',
                            title: 'sw.example.title',
                            color: '#189EFF',
                        },
                    },
                },
                $router: {
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

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
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

        const iconComponent = wrapper.find('sw-icon-stub');
        expect(iconComponent.exists()).toBe(false);
    });

    it('should render the module icon when slot "smart-bar-icon" is not filled', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const iconComponent = wrapper.find('sw-icon-stub');
        expect(iconComponent.exists()).toBe(true);
        expect(iconComponent.attributes()).toHaveProperty('name');
        expect(iconComponent.attributes().name).toBe('default-object-plug');
        expect(iconComponent.attributes()).toHaveProperty('color');
        expect(iconComponent.attributes().color).toBe('#189EFF');
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

    it('should render the tabs when slot is filled', async () => {
        const wrapper = await createWrapper({
            'page-tabs': `
<sw-tabs-item :route="{ name: 'tab.one' }">
    Tab 1
</sw-tabs-item>

<sw-tabs-item :route="{ name: 'tab.two' }">
    Tab 2
</sw-tabs-item>

<sw-tabs-item :route="{ name: 'tab.three' }">
    Tab 3
</sw-tabs-item>
            `,
        });

        await flushPromises();

        const tabsContent = wrapper.find('.sw-tabs__content');
        expect(tabsContent.exists()).toBe(true);

        const routerLinksStubs = wrapper.findAll('.router-link');
        expect(routerLinksStubs).toHaveLength(3);

        expect(routerLinksStubs.at(0).text()).toBe('Tab 1');
        expect(routerLinksStubs.at(1).text()).toBe('Tab 2');
        expect(routerLinksStubs.at(2).text()).toBe('Tab 3');
    });

    it('should not render the tabs when slot is empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tabsContent = wrapper.find('.sw-tabs__content');
        expect(tabsContent.exists()).toBe(false);
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
