import { shallowMount } from '@vue/test-utils';
import swFlowListFlowTemplates from 'src/module/sw-flow/view/listing/sw-flow-list-flow-templates';
import 'src/app/component/utils/sw-internal-link';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

Shopware.Component.register('sw-flow-list-flow-templates', swFlowListFlowTemplates);

const mockData = [
    {
        id: '44de136acf314e7184401d36406c1e90',
        name: 'test flow template',
        config: {
            eventName: 'checkout.order.placed',
        },
    },
];

async function createWrapper(privileges = [], propsData = {}) {
    return shallowMount(await Shopware.Component.build('sw-flow-list-flow-templates'), {
        propsData,
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25,
                },
            },
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: jest.fn((criteria) => {
                        return Promise.resolve(new EntityCollection('', '', Context.api, criteria, mockData, 1));
                    }),
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
            searchRankingService: {},
        },
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
            'sw-card': true,
            'sw-internal-link': await Shopware.Component.build('sw-internal-link'),
            'router-link': {
                props: ['to'],
                // eslint-disable-next-line no-template-curly-in-string
                template: '<a :href="`${to.name}/${to.params.flowTemplateId}`">asdf</a>',
            },
            'sw-icon': true,
            'sw-button': true,
            'sw-entity-listing': await Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-context-menu-item': true,
            'sw-data-grid-skeleton': true,
            'sw-pagination': true,
            'sw-empty-state': true,
            'sw-search-bar': true,
        },
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

        expect(createFlowLink.classes()).toContain('sw-internal-link--disabled');
    });

    it('should be able to redirect to create flow page from flow template', async () => {
        const wrapper = await createWrapper([
            'flow.creator',
        ]);
        await flushPromises();

        const link = wrapper.find('.sw-flow-list-my-flows__content__create-flow-link');

        expect(link.attributes('href')).toBe('sw.flow.create/44de136acf314e7184401d36406c1e90');
    });

    it('should be able to view detail flow template', async () => {
        const wrapper = await createWrapper([
            'flow.creator',
        ]);

        await flushPromises();
        await wrapper.find('.sw-flow-list-my-flows__content__update-flow-template-link').trigger('click');

        const routerPush = wrapper.vm.$router.push;

        expect(routerPush).toHaveBeenLastCalledWith({
            name: 'sw.flow.detail',
            params: { id: '44de136acf314e7184401d36406c1e90' },
            query: {
                type: 'template',
            },
        });

        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.onEditFlow({});
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledTimes(0);
    });

    it('provides a metaInfo object containing a title', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.$options.$createTitle = () => 'foo-bar';

        expect(wrapper.vm.$options.metaInfo()).toMatchObject({
            title: wrapper.vm.$options.$createTitle(),
        });
    });

    it('should set searchTerm to criteria', async () => {
        const wrapper = await createWrapper([], {
            searchTerm: 'test-term',
        });

        expect(wrapper.vm.flowTemplateRepository.search).toHaveBeenNthCalledWith(1, expect.objectContaining({
            term: 'test-term',
        }));
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
