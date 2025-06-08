export namespace EntitySchemaTypes {
    type readProtected = readonly string[];
    type writeProtected = readonly string[];

    export interface IPropertyFlags {
        primary_key?: boolean,
        required?: boolean,
        read_protected?: readonly readProtected[],
        write_protected?: readonly writeProtected[],
        cascade_delete?: boolean,
        translatable?: boolean,
        computed?: boolean,
        allow_html?: boolean,
        restrict_delete?: boolean,
        search_ranking?: number,
        runtime?: boolean,
        set_null_on_delete?: boolean,
        inherited?: boolean,
        deprecated?: unknown,
        reversed_inherited?: string,
        extension?: boolean
    }

    export type propertyTypes =
        'boolean'|
        'string'|
        'uuid'|
        'date'|
        'text'|
        'json_list'|
        'association'|
        'blob'|
        'json_object'|
        'int'|
        'float'|
        'password'|
        'Shopware\\Core\\Framework\\DataAbstractionLayer\\Field\\RemoteAddressField';

    export type relations = 'one_to_one'|'many_to_one'|'one_to_many'|'many_to_many';

    export interface IProperty {
        type: propertyTypes,
        flags: IPropertyFlags|readonly [],
        relation?: relations,
        entity?: string
    }

    export interface IProperties {
        [key: string]: IProperty
    }

    export interface IEntityDefinition {
        entity: string,
        properties: IProperties
    }

    export interface IEntitySchema {
        [key: string]: IEntityDefinition
    }
}
