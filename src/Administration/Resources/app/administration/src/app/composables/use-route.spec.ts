/**
 * @sw-package framework
 */
import { createApp, defineComponent, h } from 'vue';
import { createMemoryHistory, createRouter, type RouteLocationNormalizedLoaded } from 'vue-router';
import useRoute from './use-route';

describe('src/app/composables/use-route', () => {
    it("returns the current route of the app's router", async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/product/:id', name: 'sw.product.detail', component: { render: () => null } }],
        });
        await router.push('/product/42');

        let route: RouteLocationNormalizedLoaded | undefined;
        createApp(
            defineComponent({
                setup() {
                    route = useRoute();

                    return () => h('div');
                },
            }),
        )
            .use(router)
            .mount(document.createElement('div'));

        expect(route?.name).toBe('sw.product.detail');
        expect(route?.params).toEqual({ id: '42' });
    });
});
