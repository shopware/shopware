import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import type { ContentElementNode } from 'src/core/service/content-element.types';

/**
 * A `content_layout` entity whose `layout` field carries the canonical element type.
 *
 * @private
 * @sw-package discovery
 */
export type ContentLayoutEntity = Omit<Entity<'content_layout'>, 'layout'> & {
    layout: ContentElementNode[];
};

/**
 * @private
 * @sw-package discovery
 */
export type ContentLayoutRepository = {
    create: (context: apiContext) => ContentLayoutEntity;
    get: (id: string, context: apiContext, criteria: CriteriaType) => Promise<ContentLayoutEntity | null>;
    save: (layout: ContentLayoutEntity, context: apiContext) => Promise<void>;
};

/**
 * The single typed entry point for `content_layout` reads. The generated entity schema types the
 * `layout` field as `Array<unknown>`; this wrapper is the one place that names it as element nodes,
 * so no caller has to cast.
 *
 * @private
 * @sw-package discovery
 */
export function createContentLayoutRepository(repository: Repository<'content_layout'>): ContentLayoutRepository {
    return {
        create(context: apiContext): ContentLayoutEntity {
            return repository.create(context) as ContentLayoutEntity;
        },

        async get(id: string, context: apiContext, criteria: CriteriaType): Promise<ContentLayoutEntity | null> {
            const entity = (await repository.get(id, context, criteria)) as ContentLayoutEntity | null;

            if (entity && entity.layout !== undefined && entity.layout !== null && !Array.isArray(entity.layout)) {
                throw new Error(
                    `content_layout entity "${entity.id}" has a non-array layout: expected an array, received ${typeof entity.layout}.`,
                );
            }

            return entity;
        },

        async save(layout: ContentLayoutEntity, context: apiContext): Promise<void> {
            await repository.save(layout, context);
        },
    };
}
