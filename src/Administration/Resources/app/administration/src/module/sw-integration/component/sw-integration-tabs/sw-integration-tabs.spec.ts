/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';

describe('Integration tabs', () => {
    it.each([
        [
            ['integration.viewer'],
            ['sw.integration.index'],
        ],
        [
            ['oauth_client.viewer'],
            ['sw.integration.oauth'],
        ],
        [
            [
                'integration.viewer',
                'oauth_client.viewer',
            ],
            [
                'sw.integration.index',
                'sw.integration.oauth',
            ],
        ],
        [
            [],
            [],
        ],
    ])('only exposes allowed tabs for %j', async (privileges, routes) => {
        const push = jest.fn();
        const wrapper = shallowMount(await wrapTestComponent('sw-integration-tabs', { sync: true }), {
            global: {
                provide: { acl: { can: (key: string) => privileges.includes(key) } },
                mocks: { $route: { name: routes[0] }, $router: { push } },
                stubs: {
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: [
                            'items',
                            'defaultItem',
                        ],
                        template: '<nav />',
                    },
                },
            },
        });

        try {
            const tabs = wrapper.getComponent({ name: 'mt-tabs' });
            const items = tabs.props('items') as { name: string; onClick: () => void }[];
            expect(items.map((item) => item.name)).toEqual(routes);
            expect(tabs.props('defaultItem')).toBe(routes[0]);
            items.forEach((item) => {
                item.onClick();
                expect(push).toHaveBeenLastCalledWith({ name: item.name });
            });
        } finally {
            wrapper.unmount();
        }
    });
});
