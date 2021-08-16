import { AxiosResponse } from 'axios';
import { Context } from '../service/login.service';
import { Criteria, CriteriaDefinition } from './criteria.data.';
import { EntityCollection } from './entity-collection.data';
import { Entity } from './entity.data';

export class EntityHydrator {
    hydrateSearchResult(
        route: string,
        entityName: string,
        response: AxiosResponse,
        context: Context,
        criteria: Criteria
    ): EntityCollection;

    hydrate(
        route: string,
        entityName: string,
        data: any,
        context: Context,
        criteria: Criteria
    ): EntityCollection;

    hydrateEntity(
        entityName: string,
        row: object,
        response: any,
        context: Context,
        criteria: Criteria
    ): Entity;

    hydrateToOne(
        criteria: Criteria,
        property: string,
        value: any,
        response: any,
        context: Context
    ): Entity;

    getAssociationCriteria(criteria: Criteria, property: string): Criteria;

    hydrateToMany(
        criteria: Criteria,
        property: string,
        value: any[] | null,
        entity: Entity,
        context: Context,
        response: any
    ): EntityCollection;

    getIncluded(entity: Entity, id: string, response: any): object | undefined;

    hydrateExtensions(
        id: string,
        relationship: any,
        schema: any,
        response: any,
        context: Context,
        criteria: Criteria
    ): object;
}
