/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

export function createLandingPages(count, offset = 0) {
    return Array.from({ length: count }, (_, index) => ({ id: `id-${offset + index}` }));
}

export function createSearchResult(items, total) {
    const result = [...items];
    result.total = total;

    return result;
}

export async function createWrapper(search = () => Promise.resolve([{ id: '1a' }])) {
    const routes = [
        {
            name: 'sw.category.landingPageDetail',
            path: '/category/landingPage/:id',
        },
    ];

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    return mount(await wrapTestComponent('sw-landing-page-tree', { sync: true }), {
        global: {
            mocks: {
                $router: router,
            },
            stubs: {
                'sw-loader': true,
                'sw-skeleton': true,
                'sw-tree': {
                    props: ['items'],
                    template: `
                        <div class="sw-tree">
                            <slot name="items" :treeItems="items" :checkItem="() => {}"></slot>
                        </div>
                    `,
                },
                'sw-tree-item': {
                    props: ['item'],
                    template: `
                        <div class="sw-tree-item">
                            <slot name="actions" :toolTip="{ delay: 300, message: 'jest', active: true}"></slot>
                        </div>
                    `,
                },
                'sw-context-button': true,
                'sw-context-menu-item': true,
            },
            provide: {
                syncService: {},
                repositoryFactory: {
                    create: () => ({
                        search,
                    }),
                },
            },
        },
        props: {
            currentLanguageId: '1a2b3c',
        },
    });
}

export function setupLandingPageTreeHooks() {
    let oldSystemLanguageId = null;

    beforeEach(async () => {
        global.activeAclRoles = ['landing_page.creator', 'landing_page.editor'];

        Shopware.Store.get('swCategoryDetail').$reset();

        // this is normally set by the shopware runtime
        // but needed for this unit tests because the component relies on this value.
        oldSystemLanguageId = Shopware.Context.api.systemLanguageId;
        Shopware.Context.api.systemLanguageId = '1a2b3c';
    });

    afterEach(async () => {
        Shopware.Context.api.systemLanguageId = oldSystemLanguageId;
    });
}
