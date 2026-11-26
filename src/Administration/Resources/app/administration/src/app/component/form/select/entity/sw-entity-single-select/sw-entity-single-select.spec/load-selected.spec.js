/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const { Criteria } = Shopware.Data;

const entities = Array.from({ length: 30 }, (_, index) => ({
    id: `entity${index + 1}`,
    name: `Entity ${index + 1}`,
}));

function createRepository() {
    const requestedCriteria = [];

    const search = (criteria) => {
        let result = entities;

        if (criteria.ids.length > 0) {
            result = result.filter((entity) => criteria.ids.includes(entity.id));
        }

        if (criteria.term) {
            result = result.filter((entity) => entity.name.includes(criteria.term));
        }

        const offset = (criteria.page - 1) * criteria.limit;

        return Promise.resolve(
            new EntityCollection(
                '/test',
                'test',
                null,
                criteria,
                result.slice(offset, offset + criteria.limit),
                result.length,
            ),
        );
    };

    return {
        requestedCriteria,
        search,
        get: (id, context, criteria) => {
            requestedCriteria.push(criteria);
            criteria.setIds([id]);

            return search(criteria).then((result) => result.get(id));
        },
    };
}

async function createWrapper(criteria, repository) {
    return mount(await wrapTestComponent('sw-entity-single-select', { sync: true }), {
        props: {
            value: null,
            entity: 'test',
            criteria,
        },
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-select-result-list': true,
                'sw-select-result': true,
                'sw-highlight-text': true,
                'sw-loader': true,
                'sw-product-variant-info': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'mt-loader': true,
                'sw-popover': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
            },
        },
    });
}

describe('components/sw-entity-single-select load selected', () => {
    it('should load a value set from outside without the search term and page of the last search', async () => {
        const criteria = new Criteria(1, 25);
        const repository = createRepository();
        const wrapper = await createWrapper(criteria, repository);
        await flushPromises();

        criteria.setTerm('Entity 3');
        criteria.setPage(2);

        await wrapper.setProps({ value: 'entity28' });
        await flushPromises();

        expect(wrapper.emitted('update:value')).toBeUndefined();
        expect(wrapper.get('.sw-entity-single-select__selection-text').text()).toBe('Entity 28');
        expect(repository.requestedCriteria.at(-1)).not.toBe(criteria);
        expect(criteria.term).toBe('Entity 3');
        expect(criteria.page).toBe(2);
        expect(criteria.ids).toEqual([]);
    });
});
