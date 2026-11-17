/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

describe('components/sw-entity-single-select', () => {
    it('keeps loading pages without an exact total count', async () => {
        const repository = {
            search: jest.fn((criteria) => {
                const entities = [
                    { id: `${criteria.page}-1`, name: `entry ${criteria.page}-1` },
                    { id: `${criteria.page}-2`, name: `entry ${criteria.page}-2` },
                ];

                return Promise.resolve(new EntityCollection('/test', 'test', null, {}, entities, entities.length, null));
            }),
        };
        const wrapper = mount(await wrapTestComponent('sw-entity-single-select', { sync: true }), {
            global: {
                stubs: {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-highlight-text': true,
                    'sw-popover': {
                        template: '<div><slot></slot></div>',
                    },
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
                value: null,
                entity: 'test',
                resultLimit: 2,
            },
        });
        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const resultList = wrapper.findComponent({ ref: 'resultsList' });
        resultList.vm.$emit('paginate');
        await flushPromises();
        resultList.vm.$emit('paginate');
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(3);
        expect(repository.search).toHaveBeenLastCalledWith(
            expect.objectContaining({ page: 3 }),
            expect.any(Object),
            undefined,
        );
        expect(wrapper.findAll('.sw-select-result')).toHaveLength(6);
    });
});
