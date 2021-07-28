export class FilterFactory {
    constructor();

    create(entityName: string, filters: object | null): object[];

    getFilterProperties(entityName: string, accessor: string): object;
}
