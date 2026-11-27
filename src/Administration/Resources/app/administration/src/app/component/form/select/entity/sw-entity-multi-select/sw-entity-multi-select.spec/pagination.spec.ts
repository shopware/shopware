/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import type Criteria from 'src/core/data/criteria.data';
import EntityCollection from 'src/core/data/entity-collection.data';

describe('components/sw-entity-multi-select', () => {
    /**
     * Results stopped loading after the second page when the search returned no total count.
     * @regression
     * @see https://github.com/shopware/shopware/pull/23053
     */
    it('keeps loading pages without an exact total count and stops after a short page', async () => {
        const repository = {
            search: jest.fn((criteria: Criteria) => {
                const page = criteria.getPage();
                const entities = [
                    { id: `${page}-1`, name: `entry ${page}-1` },
                    { id: `${page}-2`, name: `entry ${page}-2` },
                ].slice(0, page < 3 ? 2 : 1) as unknown as Entity<'product'>[];

                return Promise.resolve(
                    new EntityCollection('/product', 'product', Shopware.Context.api, null, entities, entities.length),
                );
            }),
        };
        const wrapper = mount(await wrapTestComponent('sw-entity-multi-select', { sync: true }), {
            global: {
                stubs: {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-field-error': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-highlight-text': true,
                    'mt-floating-ui': {
                        props: ['isOpened'],
                        template: '<div><slot v-if="isOpened"></slot></div>',
                    },
                    'sw-label': true,
                    'sw-product-variant-info': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-loader': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => repository,
                    },
                },
            },
            props: {
                entity: 'product',
                entityCollection: new EntityCollection('/product', 'product', Shopware.Context.api, null, []),
                resultLimit: 2,
            },
        });
        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const resultList = wrapper.findComponent({ ref: 'swSelectResultList' }) as unknown as {
            vm: { $emit: (event: string) => void };
        };
        resultList.vm.$emit('paginate');
        await flushPromises();
        resultList.vm.$emit('paginate');
        await flushPromises();
        resultList.vm.$emit('paginate');
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(3);
        expect(wrapper.findAll('.sw-select-result')).toHaveLength(5);
    });
});
