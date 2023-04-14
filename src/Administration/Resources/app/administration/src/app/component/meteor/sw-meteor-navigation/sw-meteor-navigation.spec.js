/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/meteor/sw-meteor-navigation';


async function createWrapper(customRoute, fromLink = null) {
    return shallowMount(await Shopware.Component.build('sw-meteor-navigation'), {
        propsData: { fromLink },
        stubs: {
            'router-link': {
                template: '<div class="sw-router-link"><slot></slot></div>',
                props: ['to']
            },
            'sw-icon': true
        },
        mocks: {
            $route: customRoute
        }
    });
}

describe('src/app/component/meteor/sw-meteor-navigation', () => {
    let wrapper;

    const testRoute = {
        name: 'some.test.route',
        path: '/path/to/test/route',
        params: {},
        query: {},
        meta: {
            parentPath: 'some.parent.route',
        },
        hash: '',
        fullPath: '/path/to/test/route',
        matched: [],
    };

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should display the back link', async () => {
        wrapper = await createWrapper(testRoute);

        const routerLink = wrapper.get('.sw-meteor-navigation__link');

        expect(routerLink.props('to')).toEqual({
            name: testRoute.meta.parentPath,
        });

        expect(routerLink.text()).toEqual('sw-meteor.navigation.backButton');
    });

    it('should not display the back link when no parent exists', async () => {
        const testRouteWithoutMeta = { ...testRoute };
        testRouteWithoutMeta.meta = {};

        wrapper = await createWrapper(testRouteWithoutMeta);

        const routerLink = wrapper.find('.sw-meteor-navigation__link');

        expect(routerLink.exists()).toBe(false);
    });

    it('it overrides parent path with given fromLink', async () => {
        const fromLink = {
            name: 'from.link',
            path: '/path/to/from/link',
            params: {},
            query: {},
            meta: {},
            hash: '',
            fullPath: '/path/to/from/link',
            matched: [],
        };

        wrapper = await createWrapper(testRoute, fromLink);

        const routerLink = wrapper.get('.sw-meteor-navigation__link');

        expect(routerLink.props('to')).toEqual(fromLink);
    });
});
