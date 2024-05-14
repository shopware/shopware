/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/meteor/sw-meteor-navigation';

async function createWrapper(customRoute, fromLink = null) {
    return mount(await wrapTestComponent('sw-meteor-navigation', { sync: true }), {
        props: { fromLink },
        global: {
            stubs: {
                'router-link': {
                    template: '<div class="sw-router-link"><slot></slot></div>',
                    props: ['to'],
                },
                'sw-icon': true,
            },
            mocks: {
                $route: customRoute,
            },
        },
    });
}

describe('src/app/component/meteor/sw-meteor-navigation', () => {
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

    it('should display the back link', async () => {
        const wrapper = await createWrapper(testRoute);
        await flushPromises();

        const routerLink = wrapper.getComponent('.sw-meteor-navigation__link');

        expect(routerLink.props('to')).toEqual({
            name: testRoute.meta.parentPath,
        });

        expect(routerLink.text()).toBe('sw-meteor.navigation.backButton');
    });

    it('should not display the back link when no parent exists', async () => {
        const testRouteWithoutMeta = { ...testRoute };
        testRouteWithoutMeta.meta = {};

        const wrapper = await createWrapper(testRouteWithoutMeta);

        const routerLink = wrapper.find('.sw-meteor-navigation__link');

        expect(routerLink.exists()).toBe(false);
    });

    it('overrides parent path with given fromLink', async () => {
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

        const wrapper = await createWrapper(testRoute, fromLink);

        const routerLink = wrapper.getComponent('.sw-meteor-navigation__link');

        expect(routerLink.props('to')).toEqual(fromLink);
    });
});
