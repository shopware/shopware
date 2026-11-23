/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

describe('components/sw-entity-single-select', () => {
    it('does not send the selected id with a search started while the selection loads', async () => {
        const repository = {
            get: jest.fn((id, context, criteria) => {
                criteria.setIds([id]);
                return new Promise(() => {});
            }),
            search: jest.fn(() => Promise.resolve(new EntityCollection('/test', 'test', null, {}, [], 0, null))),
        };
        const swEntitySingleSelect = await wrapTestComponent('sw-entity-single-select', { sync: true });
        const wrapper = mount(swEntitySingleSelect, {
            global: {
                stubs: {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
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
                value: 'selected-id',
                entity: 'test',
            },
        });
        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');
        await wrapper.find('input').setValue('entry');
        await swEntitySingleSelect.methods.debouncedSearch.flush();
        await flushPromises();

        expect(repository.search).toHaveBeenLastCalledWith(
            expect.objectContaining({ ids: [], term: 'entry' }),
            expect.any(Object),
            undefined,
        );
    });
});
