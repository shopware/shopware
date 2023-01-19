import path from 'path';
import {InterfaceDeclarationStructure, OptionalKind, Project} from 'ts-morph';
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

        // add the Entities interface to the namespace with all entities
        entitySchemaNamespace.addInterface({
            name: 'Entities',
            properties: Object.keys(entitySchema).map((key) => ({ name: key, type: key })),
        });

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

            return {
                name: entityName,
                properties: properties,
            }
        });

        entitySchemaNamespace.addInterfaces(entityInterfaces);

        entitiesDeclarationFile.saveSync();
    }

    convertPropertyType(propertyKey: string, property: EntitySchemaTypes.IProperty, definition: EntitySchemaTypes.IEntityDefinition): string {
        const mappingMatrix: { [key: string]: () => string } = {
            boolean: () => 'boolean',
            string: () => 'string',
            uuid: () => 'string', // could be more explicit with an UUID type
            date: () => 'string', // could be more explicit with an date type
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
}
