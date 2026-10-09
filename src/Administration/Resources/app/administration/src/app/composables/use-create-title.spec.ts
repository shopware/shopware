/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import useCreateTitle from './use-create-title';

async function createTitleIn(meta: Record<string, unknown>) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', name: 'page', component: { template: '<div />' }, meta },
        ],
    });

    let createTitle: ((identifier?: string | null, ...additional: string[]) => string) | null = null;

    mount(
        defineComponent({
            template: '<div />',
            setup() {
                createTitle = useCreateTitle();
            },
        }),
        { global: { plugins: [router] } },
    );

    await router.push('/');
    await router.isReady();

    return createTitle as unknown as (identifier?: string | null, ...additional: string[]) => string;
}

describe('src/app/composables/use-create-title', () => {
    it('builds the title most specific part first', async () => {
        const createTitle = await createTitleIn({ $module: { title: 'sw-product.general.mainMenuItemGeneral' } });

        expect(createTitle('Shirt')).toBe(
            'Shirt | sw-product.general.mainMenuItemGeneral | global.sw-admin-menu.textShopwareAdmin',
        );
    });

    it('omits an absent identifier', async () => {
        const createTitle = await createTitleIn({ $module: { title: 'sw-product.general.mainMenuItemGeneral' } });

        expect(createTitle()).toBe('sw-product.general.mainMenuItemGeneral | global.sw-admin-menu.textShopwareAdmin');
    });

    it('appends additional parameters', async () => {
        const createTitle = await createTitleIn({ $module: { title: 'sw-order.general.mainMenuItemList' } });

        expect(createTitle('Order', '10001')).toBe(
            '10001 | Order | sw-order.general.mainMenuItemList | global.sw-admin-menu.textShopwareAdmin',
        );
    });

    it('returns an empty title for a route that belongs to no module', async () => {
        const createTitle = await createTitleIn({});

        expect(createTitle('Shirt')).toBe('');
    });
});
