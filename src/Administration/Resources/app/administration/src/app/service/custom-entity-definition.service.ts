import Vue from 'vue';

interface AdminUiFieldsRef {
    ref: string,
}

interface ColumnRef extends AdminUiFieldsRef {
    hidden?: boolean,
}

type AdminUiCardsConfig = {
    name: string,
    fields: AdminUiFieldsRef[],
}

type AdminTabsConfig = {
    name: string,
    cards: AdminUiCardsConfig[],
}

type AdminUiDetailConfig = {
    tabs: AdminTabsConfig[],
}

type AdminUiListingConfig = {
    columns: ColumnRef[],
}

type AdminUiConfig = {
    navigationParent: string,
    position: number,
    detail: AdminUiDetailConfig,
    listing: AdminUiListingConfig,
}

type CustomEntityConfig = {
    entity: string,
    properties: [],
    flags: {
        'admin-ui': AdminUiConfig,
    },
}

type NavigationMenuEntry = {
    label: string,
    moduleType: string,
    path: string,
    position: number,
    parent: string,
}

export default class CustomEntityDefinitionService {
    #state = Vue.observable({
        customEntityDefinitions: [] as CustomEntityConfig[],
    });

    addConfig(adminUiConfig: CustomEntityConfig) {
        this.#state.customEntityDefinitions.push(adminUiConfig);
    }

    getConfigByName(name: string): Readonly<CustomEntityConfig | undefined> {
        return this.#state.customEntityDefinitions.find(entityConfig => entityConfig.entity === name);
    }

    getAllConfigs(): Readonly<CustomEntityConfig[]> {
        return this.#state.customEntityDefinitions;
    }

    hasConfigWithAdminUi(name: string) {
        return this.#state.customEntityDefinitions.some(entityConfig => {
            return entityConfig.entity === name && entityConfig.flags['admin-ui'];
        });
    }

    getMenuEntries(): Readonly<NavigationMenuEntry[]> {
        const filteredCustomEntities = this.#state.customEntityDefinitions.map(entityConfig => {
            return { name: entityConfig.entity, adminUi: entityConfig.flags['admin-ui'] };
        }).filter(config => config.adminUi);

        return filteredCustomEntities.map(entityConfig => {
            return {
                id: `custom-entity/${entityConfig.name}`,
                label: `${entityConfig.name}.moduleTitle`,
                moduleType: 'plugin',
                path: 'sw.custom.entity.index',
                params: {
                    entityName: entityConfig.name,
                },
                position: entityConfig.adminUi.position,
                parent: entityConfig.adminUi.navigationParent,
            };
        });
    }
}
