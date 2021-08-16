import { Entity } from './entity.data';

export interface DeleteErrorDefinition {
    error: string;
    entityName: string;
    id: string;
}

export class ErrorResolver {
    resetApiErrors(): void;

    handleWriteErrors(args: { errors: any }, changeset: any): void;

    handleDeleteError(errors: DeleteErrorDefinition[]): void;

    reduceErrorsByWriteIndex(errors: any): { system: { [key: string]: any } };

    addSystemErrors(systemErrors: any[]): void;

    handleErrors(writeErrors: any, changeset: any): void;

    resolveError(
        field: string,
        error: any,
        definition: any,
        entity: Entity
    ): void;

    buildAssociationChangeset(
        entity: Entity,
        changeset: any,
        error: any,
        associationName: string
    ): any[];

    resolveJsonFieldError(basePath: string, error: any): void;

    resolveOneToOneFieldError(basePath: string, error: any): void;

    private getErrorPath(entity: Entity, currentField: string): string;
}
