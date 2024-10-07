import { reactive } from 'vue';
import type Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';

const { Application } = Shopware;

type AnyEntity = Entity<keyof EntitySchema.Entities>;

type CmsSlotConfig = {
    [key: string]: {
        source: 'mapped' | 'static' | 'default';
        value: string;
        required?: boolean;
        type?: unknown;
        entity?: {
            name: string;
            criteria?: Criteria;
        };
    };
};

type EnrichedSlotData = {
    [key: string]: null | AnyEntity | (null | AnyEntity)[];
};

type CmsSlotData = {
    key: string;
    value: unknown[];
    searchCriteria: Criteria;
    context?: unknown;
};

type RuntimeSlot = EntitySchema.Entity<'cms_slot'> & {
    config: CmsSlotConfig;
    data: {
        [key: string]: CmsSlotData;
    };
};

type CmsElementConfig = {
    name: string;
    component: string;
    configComponent?: string;
    previewComponent?: string;
    label?: string;
    flag?: boolean;
    collect?: (slot: RuntimeSlot) => Record<string, CmsSlotData>;
    enrich?: <EntityName extends keyof EntitySchema.Entities>(
        slot: RuntimeSlot,
        collectionMap: { [key: string]: EntityCollection<EntityName> },
    ) => void;
    allowedPageTypes?: string[];
    defaultConfig?: unknown;
    disabledConfigInfoTextKey?: string;
    defaultData?: unknown;
    hidden?: boolean;
    removable?: boolean;
    appData?: {
        baseUrl: string;
    };
};

type CmsBlockConfig = {
    name: string;
    component: string;
    configComponent?: string;
    previewComponent?: string;
    previewImage?: string;
    appName?: string;
    category?: string;
    label?: string;
    flag?: boolean;
    hidden?: boolean;
    removable?: boolean;
    allowedPageTypes?: string[];
    defaultConfig: unknown;
    slots?: Record<
        string,
        | string
        | {
              type: string;
              default?: {
                  config?: CmsSlotConfig;
                  data?: {
                      [key: string]: {
                          source: string;
                          value: unknown;
                      };
                  };
              };
          }
    >;
};

type EntityMappings = {
    entity?: Record<string, string[]>;
    boolean?: string[];
    integer?: string[];
    number?: string[];
    string?: string[];
    [key: string]: undefined | Record<string, string[]> | string[];
};

type CmsServiceState = {
    elementRegistry: Record<string, CmsElementConfig | undefined>;
    blockRegistry: Record<string, CmsBlockConfig | undefined>;
    mappingTypesCache: Record<string, EntityMappings | undefined>;
};

type Property = {
    flags?: {
        primary_key?: boolean;
        required?: boolean;
        read_protected?: string[][];
        write_protected?: string[][];
        cascade_delete?: boolean;
        translatable?: boolean;
        computed?: boolean;
        allow_html?: boolean;
        restrict_delete?: boolean;
        search_ranking?: number;
        runtime?: boolean;
        set_null_on_delete?: boolean;
        inherited?: boolean;
        deprecated?: unknown;
        reversed_inherited?: string;
        extension?: boolean;
    };
    required?: boolean;
    type?: string;
    relation?: 'one_to_one' | 'one_to_many' | 'many_to_one' | 'many_to_many';
    entity?: string;
};

type Properties = {
    [key: string]: Property;
};

class CmsService {
    private elementRegistry = reactive<Record<string, CmsElementConfig | undefined>>({});

    private blockRegistry = reactive<Record<string, CmsBlockConfig | undefined>>({});

    private mappingTypesCache = reactive<Record<string, EntityMappings | undefined>>({});

    private cmsServiceState: CmsServiceState = reactive({
        elementRegistry: this.elementRegistry,
        blockRegistry: this.blockRegistry,
        mappingTypesCache: this.mappingTypesCache,
    });

    public registerCmsElement(config: CmsElementConfig): boolean {
        if (!config.name || !config.component || config.flag === false) {
            return false;
        }

        if (!config.collect) {
            config.collect = CmsElementCollect;
        }

        if (!config.enrich) {
            config.enrich = CmsElementEnrich;
        }

        Shopware.Application.view?.setReactive(this.elementRegistry, config.name, config);

        return true;
    }

    public getCollectFunction() {
        return CmsElementCollectWithInheritance;
    }

    public registerCmsBlock(config: CmsBlockConfig): boolean {
        if (!config.name || !config.component || config.flag === false) {
            return false;
        }

        this.blockRegistry[config.name] = config;

        return true;
    }

    public getCmsElementConfigByName(name: string): CmsElementConfig | undefined {
        return this.elementRegistry[name];
    }

    public getCmsBlockConfigByName(name: string): CmsBlockConfig | undefined {
        return this.blockRegistry[name];
    }

    public getCmsElementRegistry(): Record<string, CmsElementConfig | undefined> {
        return this.elementRegistry;
    }

    public getCmsBlockRegistry(): Record<string, CmsBlockConfig | undefined> {
        return this.blockRegistry;
    }

    public getCmsServiceState(): CmsServiceState {
        return this.cmsServiceState;
    }

    public getEntityMappingTypes(entityName: string) {
        const schema = Shopware.EntityDefinition.has(entityName) ? Shopware.EntityDefinition.get(entityName) : undefined;

        if (schema === null || typeof schema === 'undefined') {
            return {};
        }

        if (typeof this.mappingTypesCache[entityName] === 'undefined') {
            this.mappingTypesCache[entityName] = {};
            this.handlePropertyMappings(schema.properties, this.mappingTypesCache[entityName], entityName);
        }

        return this.mappingTypesCache[entityName];
    }

    public getPropertyByMappingPath(entity: unknown, propertyPath: string): unknown {
        const path = propertyPath.split('.');

        path.splice(0, 1);
        let obj = entity as { [key: string]: unknown };
        let value: unknown = null;

        // eslint-disable-next-line no-restricted-syntax
        for (const key of path) {
            if (obj === null || typeof obj !== 'object') {
                value = null;
                break;
            }

            value = (obj.translated as { [key: string]: unknown })?.[key] ?? obj[key] ?? null;

            if (typeof value === 'object') {
                obj = value as { [key: string]: unknown };
            }
        }

        return value;
    }

    public isBlockAllowedInPageType(blockName: string, pageType: string): boolean {
        const allowedPageTypes = this.blockRegistry[blockName]?.allowedPageTypes;

        if (!Array.isArray(allowedPageTypes)) {
            return true;
        }

        return allowedPageTypes.includes(pageType);
    }

    public isElementAllowedInPageType(elementName: string, pageType: string): boolean {
        const allowedPageTypes = this.elementRegistry[elementName]?.allowedPageTypes;

        if (!allowedPageTypes || !Array.isArray(allowedPageTypes)) {
            return true;
        }

        return allowedPageTypes.includes(pageType);
    }

    private addToMappingEntity(mappings: EntityMappings, propSchema: Property, pathPrefix: string, property: string): void {
        if (!mappings.entity) {
            mappings.entity = {};
        }

        if (!mappings.entity[propSchema.entity!]) {
            mappings.entity[propSchema.entity!] = [];
        }

        if (propSchema.flags?.extension) {
            mappings.entity[propSchema.entity!].push(`${pathPrefix}.extensions.${property}`);
        } else {
            mappings.entity[propSchema.entity!].push(`${pathPrefix}.${property}`);
        }
    }

    private handlePropertyMappings(
        propertyDefinitions: Properties,
        mappings: EntityMappings,
        pathPrefix: string,
        deep: boolean = true,
    ): void {
        const blocklist = [
            'parent',
            'cmsPage',
            'translations',
            'createdAt',
            'updatedAt',
        ];
        Object.keys(propertyDefinitions).forEach((property) => {
            const propSchema = propertyDefinitions[property];

            if (
                blocklist.includes(property) ||
                (Array.isArray(propSchema?.flags?.write_protected) && propSchema.type !== 'association')
            ) {
                return;
            }

            if (propSchema.type === 'association') {
                this.handleAssociationMapping(propSchema, mappings, pathPrefix, property, deep);
            } else {
                this.handlePrimitivesMapping(propSchema, mappings, pathPrefix, property);
            }
        });
    }

    private handleAssociationMapping(
        propSchema: Property,
        mappings: EntityMappings,
        pathPrefix: string,
        property: string,
        deep: boolean = true,
    ): void {
        const toOneAssociation = [
            'many_to_one',
            'one_to_one',
        ].includes(propSchema.relation!);
        const toManyAssociation = [
            'one_to_many',
            'many_to_many',
        ].includes(propSchema.relation!);

        if (toOneAssociation && propSchema.entity) {
            this.addToMappingEntity(mappings, propSchema, pathPrefix, property);

            if (deep) {
                const schema = Shopware.EntityDefinition.get(propSchema.entity);

                if (schema) {
                    this.handlePropertyMappings(schema.properties, mappings, `${pathPrefix}.${property}`, false);
                }
            }
        } else if (toOneAssociation && (propSchema as { properties: Properties }).properties) {
            this.handlePropertyMappings(
                (propSchema as { properties: Properties }).properties,
                mappings,
                `${pathPrefix}.${property}`,
                false,
            );
        } else if (toManyAssociation && propSchema.entity) {
            this.addToMappingEntity(mappings, propSchema, pathPrefix, property);
        }
    }

    private handlePrimitivesMapping(
        propSchema: Property,
        mappings: EntityMappings,
        pathPrefix: string,
        property: string,
    ): void {
        let type = propSchema.type;

        switch (type) {
            case 'uuid':
            case 'text':
            case 'date':
                type = 'string';
                break;
            case 'float':
                type = 'number';
                break;
            case 'int':
                type = 'integer';
                break;
            case 'blob':
            case 'json_object':
            case 'json_list':
            default:
                break;
        }

        if (!type) {
            return;
        }

        if (!mappings[type]) {
            mappings[type] = [];
        }

        (mappings[type] as string[]).push(`${pathPrefix}.${property}`);
    }

    static getEntityData(slot: RuntimeSlot, configKey: string): CmsSlotData {
        const entity = slot.config[configKey].entity;
        const configValue = slot.config[configKey].value;

        const entityData: CmsSlotData = {
            value: [],
            key: configKey,
            searchCriteria: new Shopware.Data.Criteria(1, 25),
            ...entity,
        };

        if (Array.isArray(configValue)) {
            const entityIds: string[] = [];
            const mediaItems = configValue as { mediaId: string }[];

            if (mediaItems.length && mediaItems[0].mediaId) {
                mediaItems.forEach((val) => {
                    entityIds.push(val.mediaId);
                });
            } else {
                entityIds.push(...configValue);
            }

            entityData.value = entityIds;
        } else {
            entityData.value = [configValue];
        }

        entityData.searchCriteria = entity?.criteria ? entity.criteria : new Shopware.Data.Criteria(1, 25);

        return entityData;
    }
}

function CmsElementCollect(slot: RuntimeSlot) {
    const criteriaList: Record<string, CmsSlotData> = {};

    let entityCount = 0;
    Object.keys(slot.config).forEach((key) => {
        if (
            [
                'mapped',
                'default',
            ].includes(slot.config[key].source)
        ) {
            return;
        }

        const entity = slot.config[key].entity;

        if (entity && slot.config[key].value) {
            const entityKey = `entity-${entity.name}-${entityCount}`;
            entityCount += 1;

            const slotData = CmsService.getEntityData(slot, key);

            slotData.searchCriteria.setIds(slotData.value as string[]);

            criteriaList[entityKey] = slotData;
        }
    });

    return criteriaList;
}

function CmsElementCollectWithInheritance(slot: RuntimeSlot) {
    const context = {
        ...Shopware.Context.api,
        inheritance: true,
    };

    const criteriaList: { [key: string]: CmsSlotData } = {};

    let entityCount = 0;
    Object.keys(slot.config).forEach((configKey) => {
        if (
            [
                'mapped',
                'default',
            ].includes(slot.config[configKey].source)
        ) {
            return;
        }

        const entity = slot.config[configKey].entity;
        const value = slot.config[configKey].value;

        if (entity && value) {
            const entityKey = `${entity.name}-${entityCount}`;
            entityCount += 1;

            const entityData: CmsSlotData = {
                value: [value].flat(),
                key: configKey,
                searchCriteria: entity.criteria ? entity.criteria : new Shopware.Data.Criteria(1, 25),
                ...entity,
            };

            entityData.searchCriteria.setIds(entityData.value as string[]);
            entityData.context = context;

            criteriaList[`entity-${entityKey}`] = entityData;
        }
    });

    return criteriaList;
}

function CmsElementEnrich<EntityName extends keyof EntitySchema.Entities>(
    slot: RuntimeSlot,
    collectionMap: { [key: string]: EntityCollection<EntityName> },
) {
    if (Object.keys(collectionMap).length < 1) {
        return;
    }

    let entityCount = 0;
    Object.keys(slot.config).forEach((configKey) => {
        const entity = slot.config[configKey].entity;

        if (!entity) {
            return;
        }

        const collectionKey = `entity-${entity.name}-${entityCount}`;
        const collection = collectionMap[collectionKey];

        if (!collection) {
            return;
        }

        entityCount += 1;

        const slotConfigValue = slot.config[configKey].value;
        const slotData = slot.data as unknown as EnrichedSlotData;

        if (Array.isArray(slot.config[configKey].value)) {
            slotData[configKey] = [];

            (slotConfigValue as unknown as string[]).forEach((value) => {
                (slotData[configKey] as EntitySchema.Entity<EntityName>[]).push(collection.get(value) as Entity<EntityName>);
            });
        } else {
            slotData[configKey] = collection.get(slotConfigValue);
        }
    });
}

Application.addServiceProvider('cmsService', () => new CmsService());

/**
 * @private
 * @package buyers-experience
 */
export { CmsService, type CmsElementConfig, type CmsBlockConfig, type CmsSlotConfig, type RuntimeSlot };
