import { shallowMount } from '@vue/test-utils';

function createWrapper(customRoute = {}) {
    return shallowMount(Shopware.Component.build('sw-meteor-navigation'), {
        propsData: {},
        stubs: {
            'router-link': {
                template: '<div class="sw-router-link"><slot></slot></div>',
                props: ['to']
            },
            'sw-icon': true
        },
        mocks: {
            $tc: v => v,
            $route: customRoute
        },
        provide: {}
    });
}

describe('src/app/component/meteor/sw-meteor-navigation', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => {
        Shopware.Feature.init({
            FEATURE_NEXT_12608: true
        });

        await import('src/app/component/meteor/sw-meteor-navigation');
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the back link', async () => {
        wrapper = await createWrapper({
            meta: {
                parentPath: 'test.parent.path'
            }
        });

        const routerLink = wrapper.find('.sw-meteor-navigation__link');

        expect(routerLink.exists()).toBe(true);
        expect(routerLink.props()).toHaveProperty('to');
        expect(routerLink.props().to).toEqual({
            name: 'test.parent.path'
        });

        expect(routerLink.text()).toEqual('sw-meteor.navigation.backButton');
    });

    it('should not display the back link when no parent exists', async () => {
        wrapper = await createWrapper({
            meta: {}
        });

        const routerLink = wrapper.find('.sw-meteor-navigation__link');

        expect(routerLink.exists()).toBe(false);
    });
});
