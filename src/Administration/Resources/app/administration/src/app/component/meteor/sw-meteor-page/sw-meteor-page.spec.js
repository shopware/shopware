/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/meteor/sw-meteor-page';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

async function createWrapper(slotsData = {}) {
    return shallowMount(await Shopware.Component.build('sw-meteor-page'), {
        stubs: {
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-help-center': true,
            'sw-meteor-page-context': true,
            'sw-meteor-navigation': {
                props: ['fromLink'],
                template: '<div class="sw-meteor-navigation"></div>',
            },
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'router-link': true
        },

        mocks: {
            $route: {
                meta: {
                    $module: {
                        icon: 'default-object-plug',
                        title: 'sw.example.title',
                        color: '#189EFF'
                    }
                }
            },
            $router: {
                resolve() {
                    return {
                        resolved: {
                            matched: [],
                        }
                    };
                }
            },
        },
        slots: slotsData,
        propsData: {
            fromLink: {
                name: 'path.to.from.link',
            },
        },
    });
}

describe('src/app/component/meteor/sw-meteor-page', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be in full width', async () => {
        await wrapper.setProps({
            fullWidth: true
        });

        expect(wrapper.classes()).toContain('sw-meteor-page--full-width');
    });

    it('should hide the icon', async () => {
        await wrapper.setProps({
            hideIcon: true
        });

        const iconComponent = wrapper.find('sw-icon-stub');
        expect(iconComponent.exists()).toBe(false);
    });

    it('should render the module icon when slot "smart-bar-icon" is not filled', async () => {
        const iconComponent = wrapper.find('sw-icon-stub');
        expect(iconComponent.exists()).toBe(true);
        expect(iconComponent.attributes()).toHaveProperty('name');
        expect(iconComponent.attributes().name).toEqual('default-object-plug');
        expect(iconComponent.attributes()).toHaveProperty('color');
        expect(iconComponent.attributes().color).toEqual('#189EFF');
    });

    [
        'search-bar',
        'smart-bar-back',
        'smart-bar-icon',
        'smart-bar-header',
        'smart-bar-header-meta',
        'smart-bar-description',
        'smart-bar-actions',
        'smart-bar-context-buttons'
    ].forEach(slotName => {
        it(`should render the content of the slot "${slotName}"`, async () => {
            wrapper = await createWrapper({
                [slotName]: '<div id="test-slot">This slot works</div>'
            });

            const testSlot = wrapper.find('#test-slot');

            expect(testSlot.exists()).toBe(true);
            expect(testSlot.text()).toBe('This slot works');
        });
    });

    it('should render the meteor navigation component when the slot "smart-bar-back" is not used', () => {
        const navigationComponent = wrapper.get('.sw-meteor-navigation');

        expect(navigationComponent.vm).toBeTruthy();

        expect(navigationComponent.props('fromLink')).toEqual({
            name: 'path.to.from.link',
        });
    });

    it('should not render the meteor navigation component when the slot "smart-bar-back" is not used', async () => {
        wrapper = await createWrapper({
            'smart-bar-back': '<div id="test-slot">This slot works</div>'
        });

        const navigationComponent = wrapper.find('sw-meteor-navigation-stub');
        expect(navigationComponent.exists()).toBe(false);
    });

    it('should render the title of the page when slot "smart-bar-header" is not filled', async () => {
        const title = wrapper.find('.sw-meteor-page__smart-bar-title');

        expect(title.exists()).toBe(true);
        expect(title.text()).toEqual('sw.example.title');
    });

    it('should render the tabs when slot is filled', async () => {
        wrapper = await createWrapper({
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
            `
        });

        const tabsContent = wrapper.find('.sw-tabs__content');
        expect(tabsContent.exists()).toBe(true);

        const routerLinksStubs = wrapper.findAll('router-link-stub');
        expect(routerLinksStubs.length).toBe(3);

        expect(routerLinksStubs.at(0).text()).toEqual('Tab 1');
        expect(routerLinksStubs.at(1).text()).toEqual('Tab 2');
        expect(routerLinksStubs.at(2).text()).toEqual('Tab 3');
    });

    it('should not render the tabs when slot is empty', async () => {
        const tabsContent = wrapper.find('.sw-tabs__content');
        expect(tabsContent.exists()).toBe(false);
    });

    it('should render the content', async () => {
        wrapper = await createWrapper({
            default: '<p>Lorem Ipsum</p>'
        });

        const pageContent = wrapper.find('.sw-meteor-page__content');
        expect(pageContent.text()).toBe('Lorem Ipsum');
    });

    it('should contain sw-help-center and sw-notification-center', async () => {
        const globalActions = wrapper.get('.sw-meteor-page__head-area-global-actions');

        expect(globalActions.get('sw-help-center-stub').exists()).toBe(true);
        expect(globalActions.get('sw-notification-center-stub').exists()).toBe(true);
    });
});
