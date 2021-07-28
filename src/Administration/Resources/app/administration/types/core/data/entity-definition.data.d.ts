export class EntityDefinition {
    constructor(definition: { entity: object; properties: object });

    getEntity(): object;

    getPrimaryKeyFields(): object;

    getAssociationFields(): object;

    getToManyAssociations(): object;

    getToOneAssociations(): object;

    getTranslatableFields(): object;

    getRequiredFields(): object;

    filterProperties(filter: (property: object) => boolean): object;

    getField(name: string): object | undefined;

    forEachField(
        callback: (
            property: object,
            propertyName: string,
            properties: object
        ) => void
    ): void;

    isScalarField(field: object): boolean;

    isJsonField(field: object): boolean;

    isJsonObjectField(field: object): boolean;

    isJsonListField(field: object): boolean;

    isToManyAssociation(field: object): boolean;

    isToOneAssociation(field: object): boolean;

    isTranslatableField(field: object): boolean;
}
