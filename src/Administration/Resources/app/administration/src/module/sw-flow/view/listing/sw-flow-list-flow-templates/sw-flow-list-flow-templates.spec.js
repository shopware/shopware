/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

const mockData = [
    {
        id: '44de136acf314e7184401d36406c1e90',
        name: 'test flow template',
        config: {
            eventName: 'checkout.order.placed',
        },
    },
];
const flowTemplateRepositorySearchMock = jest.fn((criteria) => {
    return Promise.resolve(new EntityCollection('', '', Context.api, criteria, mockData, 1));
});

async function createWrapper(privileges = [], props = {}) {
    return mount(await wrapTestComponent('sw-flow-list-flow-templates', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                },
                'router-link': true,
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-context-menu-item': true,
                'sw-data-grid-skeleton': true,
                'sw-pagination': true,
                'sw-search-bar': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
                'sw-bulk-edit-modal': true,
                'sw-checkbox-field': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: flowTemplateRepositorySearchMock,
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                searchRankingService: {
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
            mocks: {
                $router: createRouter({
                    routes: [
                        {
                            name: 'sw.flow.list',
                            path: '/',
                            component: { template: '<div />' },
                        },
                        {
                            name: 'sw.flow.index',
                            path: '/sw/flow/index',
                            component: { template: '<div />' },
                        },
                        {
                            name: 'sw.flow.create',
                            path: '/sw/flow/create/:flowTemplateId?',
                            component: { template: '<div />' },
                        },
                        {
                            name: 'sw.flow.detail',
                            path: '/sw/flow/detail/:id',
                            component: { template: '<div />' },
                        },
                    ],
                    history: createMemoryHistory(),
                }),
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                    meta: {
                        $module: {
                            icon: 'solid-content',
                        },
                    },
                },
            },
        },
        props,
    });
}

describe('module/sw-flow/view/listing/sw-flow-list-flow-templates', () => {
    it('should be able to create a flow from template', async () => {
        const wrapper = await createWrapper([
            'flow.creator',
        ]);
        await flushPromises();

        const createFlowLink = wrapper.find('.sw-flow-list-my-flows__content__create-flow-link');
        expect(createFlowLink.exists()).toBe(true);

        expect(createFlowLink.attributes().disabled).toBeUndefined();
    });

    it('should not be able to create a flow from template', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);
        await flushPromises();

        const createFlowLink = wrapper.find('.sw-flow-list-my-flows__content__create-flow-link');
        expect(createFlowLink.exists()).toBe(true);

        expect(createFlowLink.classes()).toContain('mt-link--disabled');
    });

    it('should be able to view detail flow template', async () => {
        const wrapper = await createWrapper([
            'flow.creator',
        ]);
        await flushPromises();

        const routerPushSpy = jest.spyOn(wrapper.vm.$router, 'push');

        await wrapper.find('.sw-flow-list-my-flows__content__update-flow-template-link').trigger('click');

        expect(routerPushSpy).toHaveBeenLastCalledWith({
            name: 'sw.flow.detail',
            params: { id: '44de136acf314e7184401d36406c1e90' },
            query: {
                type: 'template',
            },
        });

        routerPushSpy.mockClear();
        wrapper.vm.onEditFlow({});
        await flushPromises();

        expect(routerPushSpy).toHaveBeenCalledTimes(0);
    });

    it('provides a metaInfo object containing a title', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$createTitle = () => 'foo-bar';

        expect(wrapper.vm.$options.metaInfo.call(wrapper.vm)).toMatchObject({
            title: 'foo-bar',
        });
    });

    it('should set searchTerm to criteria', async () => {
        await createWrapper([], {
            searchTerm: 'test-term',
        });
        await flushPromises();

        expect(flowTemplateRepositorySearchMock).toHaveBeenNthCalledWith(
            1,
            expect.objectContaining({
                term: 'test-term',
            }),
        );
    });

    it('should correctly align table columns', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__header').exists()).toBe(true);

        const headers = wrapper.findAll('.sw-data-grid__header th');
        expect(headers).toHaveLength(3);

        // name
        expect(headers.at(0).classes()).toContain('sw-data-grid__cell--align-left');
        // description
        expect(headers.at(1).classes()).toContain('sw-data-grid__cell--align-left');
        // createFlow
        expect(headers.at(2).classes()).toContain('sw-data-grid__cell--align-right');
    });
});
