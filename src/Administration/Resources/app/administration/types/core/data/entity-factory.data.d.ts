import { Context } from '../service/login.service';
import { Entity } from './entity.data';
import { EntityCollection } from './entity-collection.data';

export class EntityFactory {
    create(entityName: string, id: string, context: Context): Entity;

    createCollection(
        entity: Entity,
        id: string,
        property: string,
        related: string,
        context: Context
    ): EntityCollection;
}
