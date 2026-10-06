/**
 * @sw-package framework
 */

import Criteria from 'src/core/data/criteria.data';
import EntityHydrator from 'src/core/data/entity-hydrator.data';

describe('entity-hydrator.data', () => {
    beforeAll(() => {
        Shopware.EntityDefinition.add('product', {
            entity: 'product',
            properties: {
                customSearchKeywords: {
                    type: 'json_list',
                    flags: {
                        inherited: true,
                    },
                },
                variation: {
                    type: 'json_list',
                    flags: {},
                },
            },
            'read-protected': false,
            'write-protected': false,
        });
    });

    function hydrate(attributes: Record<string, unknown>) {
        const row = {
            id: 'c7ee62a63f3a49dc95d86b36e61cf922',
            type: 'product',
            attributes,
            links: {},
            relationships: {},
        };

        return new EntityHydrator().hydrateEntity(
            'product',
            row,
            {
                data: [row],
                included: [],
                links: {},
                aggregations: {},
            } as never,
            Shopware.Context.api,
            new Criteria(),
        );
    }

    it('preserves null for inherited JSON list fields', () => {
        const entity = hydrate({
            customSearchKeywords: null,
            variation: null,
        });

        expect(entity?.customSearchKeywords).toBeNull();
        expect(entity?.variation).toEqual([]);
    });

    it('preserves an explicit empty inherited JSON list', () => {
        const entity = hydrate({
            customSearchKeywords: [],
        });

        expect(entity?.customSearchKeywords).toEqual([]);
    });
});
