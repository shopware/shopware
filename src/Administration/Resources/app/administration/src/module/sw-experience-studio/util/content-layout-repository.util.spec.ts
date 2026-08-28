import type Repository from 'src/core/data/repository.data';
import type { ContentElementNode } from 'src/core/service/content-element.types';
import { createContentLayoutRepository } from './content-layout-repository.util';

const { Criteria } = Shopware.Data;

function createStoredEntity(elements: ContentElementNode[]): unknown {
    return {
        id: 'layout-1',
        name: 'Landing page',
        version: '1.0.0',
        rootSource: 'landing_page',
        layout: elements,
    };
}

function createFakeRepository(entity: unknown, readCalls: unknown[][] = []): Repository<'content_layout'> {
    return {
        get: (...args: unknown[]) => {
            readCalls.push(args);

            return Promise.resolve(entity);
        },
        create: () => entity,
        save: () => Promise.resolve(),
    } as unknown as Repository<'content_layout'>;
}

describe('module/sw-experience-studio/util/content-layout-repository.util', () => {
    it('reads the entity layout as content element nodes', async () => {
        const section: ContentElementNode = {
            id: 'section-1',
            component: 'Sw:Layout:Section',
            slots: {
                main: [
                    {
                        id: 'text-1',
                        component: 'Sw:Content:Text',
                        properties: {
                            text: 'Hello',
                        },
                    },
                ],
            },
        };
        const repository = createContentLayoutRepository(createFakeRepository(createStoredEntity([section])));

        const entity = await repository.get('layout-1', Shopware.Context.api, new Criteria(1, 1));

        expect(entity?.layout).toEqual([section]);
        expect(entity?.layout[0].slots?.main[0].component).toBe('Sw:Content:Text');
    });

    it('keeps element attribution on the entity read', async () => {
        const attributedElement: ContentElementNode = {
            id: 'text-1',
            component: 'Sw:Content:Text',
            dataRequirements: {
                product: {
                    entity: 'product',
                },
            },
            attributedSpecifications: {
                product: 'binding-product-detail',
            },
        };
        const repository = createContentLayoutRepository(createFakeRepository(createStoredEntity([attributedElement])));

        const entity = await repository.get('layout-1', Shopware.Context.api, new Criteria(1, 1));

        expect(entity?.layout[0].attributedSpecifications).toEqual({
            product: 'binding-product-detail',
        });
    });

    it('throws when the stored layout is not an array', async () => {
        const repository = createContentLayoutRepository(
            createFakeRepository(createStoredEntity('not-an-array' as unknown as ContentElementNode[])),
        );

        await expect(repository.get('layout-1', Shopware.Context.api, new Criteria(1, 1))).rejects.toThrow(
            'content_layout entity "layout-1" has a non-array layout: expected an array, received string.',
        );
    });

    it('passes through an entity whose layout is absent', async () => {
        const entityWithoutLayout: unknown = {
            id: 'layout-1',
            name: 'Landing page',
            version: '1.0.0',
            rootSource: 'landing_page',
        };
        const repository = createContentLayoutRepository(createFakeRepository(entityWithoutLayout));

        const entity = await repository.get('layout-1', Shopware.Context.api, new Criteria(1, 1));

        expect(entity?.layout).toBeUndefined();
    });

    it('passes through an entity whose layout is null', async () => {
        const repository = createContentLayoutRepository(
            createFakeRepository(createStoredEntity(null as unknown as ContentElementNode[])),
        );

        const entity = await repository.get('layout-1', Shopware.Context.api, new Criteria(1, 1));

        expect(entity?.layout).toBeNull();
    });

    it('forwards id, context and criteria to the entity repository', async () => {
        const readCalls: unknown[][] = [];
        const criteria = new Criteria(1, 1);
        const repository = createContentLayoutRepository(createFakeRepository(createStoredEntity([]), readCalls));

        await repository.get('layout-1', Shopware.Context.api, criteria);

        expect(readCalls).toEqual([
            [
                'layout-1',
                Shopware.Context.api,
                criteria,
            ],
        ]);
    });
});
