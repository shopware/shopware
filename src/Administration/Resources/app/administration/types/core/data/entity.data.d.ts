export class Entity {
    constructor(id: string, entityName: string, data: object);

    markAsNew(): void;

    isNew(): boolean;

    getIsDirty(): boolean;

    getOrigin(): object;

    getDraft(): object;

    getEntityName(): string;
}
