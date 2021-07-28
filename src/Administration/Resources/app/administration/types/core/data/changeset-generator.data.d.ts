import { Entity } from './entity.data';
import { EntityCollection } from './entity-collection.data';

export class ChangesetGenerator {
    getPrimaryKeyData(entity: Entity): object;

    generate(
        entity: Entity
    ): {
        changes: object | null;
        deletionQueue: any[];
    };

    recursion(entity: Entity, deletionQueue: object[]): object | null;

    handleManyToMany(
        draft: EntityCollection,
        origin: EntityCollection,
        deletionQueue: object[],
        field: object,
        entity: Entity
    ): any[];

    handleOneToMany(
        field: object,
        draft: Entity,
        origin: Entity,
        deletionQueue: object[]
    ): any[];
}
