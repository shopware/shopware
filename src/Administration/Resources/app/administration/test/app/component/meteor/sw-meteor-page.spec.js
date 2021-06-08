import { shallowMount } from '@vue/test-utils';

function createWrapper(slotsData = {}) {
    return shallowMount(Shopware.Component.build('sw-meteor-page'), {
        stubs: {
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-meteor-page-context': true,
            'sw-meteor-navigation': true,
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
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
            }
        },
        slots: slotsData
    });
}

describe('src/app/component/meteor/sw-meteor-page', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_12608'];

        await import('src/app/component/meteor/sw-meteor-page');
        await import('src/app/component/base/sw-tabs');
        await import('src/app/component/base/sw-tabs-item');
    });

    beforeEach(() => {
        wrapper = createWrapper();
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

    it('should render the module icon when slot "smart-bar-icon" is not filled', () => {
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
        it(`should render the content of the slot "${slotName}"`, () => {
            wrapper = createWrapper({
                [slotName]: '<div id="test-slot">This slot works</div>'
            });

            const testSlot = wrapper.find('#test-slot');

            expect(testSlot.exists()).toBe(true);
            expect(testSlot.text()).toBe('This slot works');
        });
    });

    it('should render the meteor navigation component when the slot "smart-bar-back" is not used', () => {
        const navigationComponent = wrapper.find('sw-meteor-navigation-stub');
        expect(navigationComponent.exists()).toBe(true);
    });

    it('should not render the meteor navigation component when the slot "smart-bar-back" is not used', () => {
        wrapper = createWrapper({
            'smart-bar-back': '<div id="test-slot">This slot works</div>'
        });

        const navigationComponent = wrapper.find('sw-meteor-navigation-stub');
        expect(navigationComponent.exists()).toBe(false);
    });

    it('should render the title of the page when slot "smart-bar-header" is not filled', () => {
        const title = wrapper.find('.sw-meteor-page__smart-bar-title');

        expect(title.exists()).toBe(true);
        expect(title.text()).toEqual('sw.example.title');
    });

    it('should render the tabs when slot is filled', () => {
        wrapper = createWrapper({
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

    it('should not render the tabs when slot is empty', () => {
        const tabsContent = wrapper.find('.sw-tabs__content');
        expect(tabsContent.exists()).toBe(false);
    });

    it('should render the content', () => {
        wrapper = createWrapper({
            default: '<p>Lorem Ipsum</p>'
        });

        const pageContent = wrapper.find('.sw-meteor-page__content');
        expect(pageContent.text()).toBe('Lorem Ipsum');
    });
});
