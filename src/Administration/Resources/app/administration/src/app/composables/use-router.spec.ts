/**
 * @sw-package framework
 */
import { createApp, defineComponent, h } from 'vue';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import useRouter from './use-router';

describe('src/app/composables/use-router', () => {
    it("returns the app's router", () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/', component: { render: () => null } }],
        });

        let injected: Router | undefined;
        createApp(
            defineComponent({
                setup() {
                    injected = useRouter();

                    return () => h('div');
                },
            }),
        )
            .use(router)
            .mount(document.createElement('div'));

        expect(injected).toBe(router);
    });
});
