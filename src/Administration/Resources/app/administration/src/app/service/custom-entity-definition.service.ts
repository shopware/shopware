import { reactive } from 'vue';

/**
 * @private
 */
export interface AdminUiFieldsRef {
    ref: string;
}

/**
 * @private
 */
export interface ColumnRef extends AdminUiFieldsRef {
    hidden?: boolean;
}

/**
 * @private
 */
export type AdminUiCardsDefinition = {
    name: string;
    fields: AdminUiFieldsRef[];
};

/**
 * @private
 */
export type AdminTabsDefinition = {
    name: string;
    cards: AdminUiCardsDefinition[];
};

/**
 * @private
 */
export type AdminUiDetailDefinition = {
    tabs: AdminTabsDefinition[];
};

/**
 * @private
 */
export type AdminUiListingDefinition = {
    columns: ColumnRef[];
};

/**
 * @private
 */
export type AdminUiDefinition = {
    navigationParent: string;
    position: number;
    icon: string;
    color: string;
    detail: AdminUiDetailDefinition;
    listing: AdminUiListingDefinition;
};

/**
 * @private
 */
export type CmsAwareDefinition = {
    name: string;
};

/**
 * @private
 */
export type CustomEntityProperties = {
    [key: string]:
        | {
              flags: Array<unknown>;
              type: string;
          }
        | undefined;
};

/**
 * @private
 */
export type CustomEntityDefinition = {
    entity: string;
    properties: CustomEntityProperties;
    flags: {
        'admin-ui': AdminUiDefinition;
        'cms-aware': CmsAwareDefinition;
    };
};

type NavigationMenuEntry = {
    label: string;
    moduleType: string;
    path: string;
    position: number;
    parent: string;
};

/**
 * @private
 * @package content
 */
export default class CustomEntityDefinitionService {
    #state = reactive({
        customEntityDefinitions: [] as CustomEntityDefinition[],
    });

    addDefinition(customEntityDefinition: CustomEntityDefinition) {
        this.#state.customEntityDefinitions.push(customEntityDefinition);
    }

    getDefinitionByName(name: string): Readonly<CustomEntityDefinition | undefined> {
        return this.#state.customEntityDefinitions.find((entityDefinition) => entityDefinition.entity === name);
    }

    getAllDefinitions(): Readonly<CustomEntityDefinition[]> {
        return this.#state.customEntityDefinitions;
    }

    hasDefinitionWithAdminUi(name: string) {
        return this.#state.customEntityDefinitions.some((entityDefinition) => {
            return entityDefinition.entity === name && entityDefinition.flags?.['admin-ui'];
        });
    }

    hasDefinitionWithCmsAware(name: string) {
        return this.#state.customEntityDefinitions.some((entityDefinition) => {
            return entityDefinition.entity === name && entityDefinition.flags?.['cms-aware']?.name;
        });
    }

    getCmsAwareDefinitions(): Readonly<CustomEntityDefinition[]> {
        return this.#state.customEntityDefinitions.filter(
            (entityDefinition) => !!entityDefinition.flags?.['cms-aware']?.name,
        );
    }

    getMenuEntries(): Readonly<NavigationMenuEntry[]> {
        const customEntityDefinitionsWithAdminUi: {
            name: string;
            adminUi: AdminUiDefinition;
        }[] = [];

        this.#state.customEntityDefinitions.forEach((entityDefinition) => {
            const adminUi = entityDefinition.flags?.['admin-ui'];

            if (!adminUi) {
                return;
            }

            customEntityDefinitionsWithAdminUi.push({
                name: entityDefinition.entity,
                adminUi,
            });
        });

        return customEntityDefinitionsWithAdminUi.map((entityDefinition) => {
            return {
                id: `custom-entity/${entityDefinition.name}`,
                label: `${entityDefinition.name}.moduleTitle`,
                moduleType: 'plugin',
                path: 'sw.custom.entity.index',
                params: {
                    entityName: entityDefinition.name,
                },
                position: entityDefinition.adminUi.position,
                parent: entityDefinition.adminUi.navigationParent,
                icon: entityDefinition.adminUi.icon,
            };
        });
    }
}
