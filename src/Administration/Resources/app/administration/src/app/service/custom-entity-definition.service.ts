import Vue from 'vue';

export interface AdminUiFieldsRef {
    ref: string,
}

export interface ColumnRef extends AdminUiFieldsRef {
    hidden?: boolean,
}

export type AdminUiCardsDefinition = {
    name: string,
    fields: AdminUiFieldsRef[],
}

export type AdminTabsDefinition = {
    name: string,
    cards: AdminUiCardsDefinition[],
}

export type AdminUiDetailDefinition = {
    tabs: AdminTabsDefinition[],
}

export type AdminUiListingDefinition = {
    columns: ColumnRef[],
}

export type AdminUiDefinition = {
    navigationParent: string,
    position: number,
    icon: string,
    color: string,
    detail: AdminUiDetailDefinition,
    listing: AdminUiListingDefinition,
}

export type CustomEntityDefinition = {
    entity: string,
    properties: [],
    flags: {
        'admin-ui': AdminUiDefinition,
    },
}

type NavigationMenuEntry = {
    label: string,
    moduleType: string,
    path: string,
    position: number,
    parent: string,
}

/**
 * @internal
 * @private
 */
export default class CustomEntityDefinitionService {
    #state = Vue.observable({
        customEntityDefinitions: [] as CustomEntityDefinition[],
    });

    addDefinition(adminUiDefinition: CustomEntityDefinition) {
        this.#state.customEntityDefinitions.push(adminUiDefinition);
    }

    getDefinitionByName(name: string): Readonly<CustomEntityDefinition | undefined> {
        return this.#state.customEntityDefinitions.find(entityDefinition => entityDefinition.entity === name);
    }

    getAllDefinitions(): Readonly<CustomEntityDefinition[]> {
        return this.#state.customEntityDefinitions;
    }

    hasDefinitionWithAdminUi(name: string) {
        return this.#state.customEntityDefinitions.some(entityDefinition => {
            return entityDefinition.entity === name && entityDefinition.flags['admin-ui'];
        });
    }

    getMenuEntries(): Readonly<NavigationMenuEntry[]> {
        const customEntityDefinitionsWithAdminUi: {name: string, adminUi: AdminUiDefinition}[] = [];

        this.#state.customEntityDefinitions.forEach(entityDefinition => {
            const adminUi = entityDefinition.flags['admin-ui'];

            if (!adminUi) {
                return;
            }

            customEntityDefinitionsWithAdminUi.push({ name: entityDefinition.entity, adminUi });
        });

        return customEntityDefinitionsWithAdminUi.map(entityDefinition => {
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
            };
        });
    }
}
