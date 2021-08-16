import { Context } from '../service/login.service';
import { Entity } from './entity.data';
import { Criteria } from './criteria.data.';

export class EntityCollection extends Array {
    constructor(
        source: string,
        entity: string,
        context: Context,
        criteria?: Criteria,
        entities?: Entity[],
        total?: number,
        aggregations?: null
    );

    first(): null | Entity;

    last(): null | Entity;

    remove(id: string): boolean;

    has(id: string): boolean;

    get(id: string): null | Entity;

    getAt(index: number): null | Entity;

    getIds(): string[];

    add(item: any): void;

    addAt(item: any, insertIndex: number): void;

    moveItem(oldIndex: number, newIndex?: number): null | Entity;

    filter(
        callback: (value: any, index: number, array: any[]) => value is string,
        scope: any
    ): EntityCollection;

    static fromCollection(collection: any): EntityCollection;
}
