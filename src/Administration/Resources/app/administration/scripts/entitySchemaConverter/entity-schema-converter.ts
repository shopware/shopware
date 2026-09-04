import path from 'path';
import {InterfaceDeclarationStructure, OptionalKind, Project, PropertySignatureStructure} from 'ts-morph';
import type { EntitySchemaTypes } from './EntitySchemaTypes';

export class EntitySchemaConverter {
    convert(entitySchema: EntitySchemaTypes.IEntitySchema, outputPath: string): void {
        const project = new Project();

        const entitiesDeclarationFile = project.createSourceFile(path.join(outputPath), '', {
            overwrite: true,
        });

        entitiesDeclarationFile.insertStatements(0, `\
/* eslint-disable */
/* THIS FILE IS AUTO GENERATED AND SHOULDN'T BE MODIFIED MANUALLY */
        `);

        // create namespace for EntitySchema
        const entitySchemaNamespace = entitiesDeclarationFile.addModule({
            name: 'EntitySchema',
            hasDeclareKeyword: true,
        });

        entitySchemaNamespace.addTypeAlias({
            name: 'EntityKey<K extends keyof EntityKeys>',
            type: `EntityKeys[K]`,
        });

        entitySchemaNamespace.addTypeAlias({
            name: 'DateTime',
            type: `string & { readonly __brand: 'DateTime' }`,
        });

        // add the Entities interface to the namespace with all entities
        entitySchemaNamespace.addInterface({
            name: 'Entities',
            properties: Object.keys(entitySchema).map((key) => ({ name: key, type: key })),
        });

        const keyProperties: OptionalKind<PropertySignatureStructure>[] = []; 

        const entityInterfaces: readonly OptionalKind<InterfaceDeclarationStructure>[] = Object.entries(entitySchema).map(([entityName, definition]) => {
            const properties = Object.entries(definition.properties)
                .map(([propertyKey, propertyInfos]) => {
                    const required = ('required' in propertyInfos.flags) ? propertyInfos.flags.required : false;

                    return {
                        name: propertyKey,
                        type: this.convertPropertyType(propertyKey, propertyInfos, definition),
                        hasQuestionToken: !required,
                    };
                });

            properties.push({
                name: 'extensions',
                type: 'Record<string, unknown>',
                hasQuestionToken: true,
            });

            // Count the number of primary keys in the entity definition. (Only count those which are not foreign keys)
            // If there is exactly one valid primary key, add it to the list of key properties for the EntityKeys interface.
            if (Object.values(definition.properties).filter((property) => 'primary_key' in property.flags && property.flags.primary_key && !property.entity).length === 1) {
                keyProperties.push({
                    name: entityName,
                    type: this.toPascalCase(entityName) + 'Id',
                });
            }

            return {
                name: entityName,
                properties: properties,
            }
        });

        // add the PrimaryKey type aliases to the namespace for all entities which have a single primary key
        for (const keyProperty of keyProperties) {
            entitySchemaNamespace.addTypeAlias({
                name: this.toPascalCase(keyProperty.name) + 'Id',
                type: `string & { readonly __brand: '${this.toPascalCase(keyProperty.name)}Id' }`,
            });
        }

        // add the EntityKeys interface to the namespace with all entities which have a single primary key
        entitySchemaNamespace.addInterface({
            name: 'EntityKeys',
            properties: keyProperties,
        });

        entitySchemaNamespace.addInterfaces(entityInterfaces);

        entitiesDeclarationFile.saveSync();
    }

    convertPropertyType(propertyKey: string, property: EntitySchemaTypes.IProperty, definition: EntitySchemaTypes.IEntityDefinition): string {
        const mappingMatrix: { [key: string]: () => string } = {
            boolean: () => 'boolean',
            string: () => 'string',
            date: () => 'DateTime',
            text: () => 'string',
            // eslint-disable-next-line @typescript-eslint/naming-convention
            json_list: () => 'Array<unknown>',
            association: () => this.hydrateAssociation(property),
            blob: () => 'string',
            // eslint-disable-next-line @typescript-eslint/naming-convention
            json_object: () => 'unknown',
            int: () => 'number',
            float: () => 'number',
            password: () => 'string',
        };

        if (propertyKey === 'translated') {
            const translatableFields = Object.entries(definition.properties).filter(([propertyKey, propertyInfos]) => {
                if (propertyInfos.flags instanceof Array) {
                    return false;
                }

                return propertyInfos.flags.translatable;
            }).map(([propertyKey]) => {
                const propertyInfos = definition.properties[propertyKey];

                return `${propertyKey}?: ${this.convertPropertyType(propertyKey, propertyInfos, definition)}`;
            })

            return '{' + translatableFields.join(', ') + '}';
        }

        if (property.type === 'uuid') {
            return `EntityKey<'${property.entity ?? definition.entity}'>`;
        }

        return mappingMatrix[property.type]?.() ?? 'unknown';
    }

    hydrateAssociation(property: EntitySchemaTypes.IProperty): string {
        // Handle notification separately because there is no entity for it
        if (property.entity === 'notification') {
            return 'unknown';
        }

        if (property.relation && property.entity) {
            const isToOne = ['one_to_one', 'many_to_one'].includes(property.relation);
            const isToMany = ['one_to_many', 'many_to_many'].includes(property.relation);

            if (isToOne) {
                return `Entity<'${property.entity}'>`;
            }

            if (isToMany) {
                return `EntityCollection<'${property.entity}'>`;
            }
        }

        return 'unknown';
    }

    toPascalCase(str: string): string {
        return str.replace(/(^\w|_\w)/g, (match) => match.replace('_', '').toUpperCase());
    }
}
