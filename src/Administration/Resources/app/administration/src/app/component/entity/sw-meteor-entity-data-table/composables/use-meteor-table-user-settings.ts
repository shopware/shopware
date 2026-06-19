/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { nextTick, reactive, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type {
    SwMeteorEntityDataTableAclService,
    SwMeteorEntityDataTableColumnChange,
    SwMeteorEntityDataTableColumnChanges,
    SwMeteorEntityDataTableNormalizedUserSettings,
    SwMeteorEntityDataTableResolvedColumn,
    SwMeteorEntityDataTableUserConfigEntity,
    SwMeteorEntityDataTableUserConfigRepository,
    SwMeteorEntityDataTableUserSettingColumn,
    SwMeteorEntityDataTableUserSettings,
} from '../sw-meteor-entity-data-table.internal-types';
import { isRecord } from '../sw-meteor-entity-data-table.utils';

type UseMeteorTableUserSettingsOptions = {
    identifier: () => string | undefined;
    resolvedColumns: ComputedRef<SwMeteorEntityDataTableResolvedColumn[]>;
};

export function useMeteorTableUserSettings(options: UseMeteorTableUserSettingsOptions): {
    tableColumnChanges: SwMeteorEntityDataTableColumnChanges;
    showOutlines: Ref<boolean>;
    showStripes: Ref<boolean>;
    enableOutlineFraming: Ref<boolean>;
    enableRowNumbering: Ref<boolean>;
    loadUserTableSettings: () => Promise<void>;
    setShowOutlines: (value: boolean) => void;
    setShowStripes: (value: boolean) => void;
    setEnableOutlineFraming: (value: boolean) => void;
    setEnableRowNumbering: (value: boolean) => void;
} {
    const { Criteria } = Shopware.Data;
    const tableColumnChanges: SwMeteorEntityDataTableColumnChanges = reactive({});
    const showOutlines = ref(true);
    const showStripes = ref(true);
    const enableOutlineFraming = ref(false);
    const enableRowNumbering = ref(false);
    const currentUserTableSetting = ref<SwMeteorEntityDataTableUserConfigEntity | null>(null);
    let isApplyingUserTableSettings = false;

    function getUserTableSettingsKey(): string {
        const identifier = options.identifier() ?? '';

        if (!identifier) {
            return '';
        }

        return identifier.startsWith('grid.setting.') ? identifier : `grid.setting.${identifier}`;
    }

    function getCurrentUserId(): string {
        return Shopware.Store.get('session').currentUser?.id ?? '';
    }

    function getAclService(): SwMeteorEntityDataTableAclService {
        return Shopware.Service('acl') as SwMeteorEntityDataTableAclService;
    }

    function getUserConfigRepository(): SwMeteorEntityDataTableUserConfigRepository {
        const repositoryFactory = Shopware.Service('repositoryFactory');

        return repositoryFactory.create('user_config');
    }

    function buildUserTableSettingsCriteria(key: string): CriteriaType {
        const criteria = new Criteria(1, 25);

        criteria.addFilter(Criteria.equals('key', key));
        criteria.addFilter(Criteria.equals('userId', getCurrentUserId()));

        return criteria;
    }

    function getFirstUserTableSetting(
        response: EntityCollection<'user_config'>,
    ): SwMeteorEntityDataTableUserConfigEntity | null {
        if ('first' in response && typeof response.first === 'function') {
            return response.first() ?? null;
        }

        return response[0] ?? null;
    }

    function hasUserConfigPermission(permission: string): boolean {
        return getAclService().can(permission);
    }

    async function loadUserTableSettings(): Promise<void> {
        const key = getUserTableSettingsKey();

        if (!key || !hasUserConfigPermission('user_config:read')) {
            return;
        }

        try {
            const userConfigRepository = getUserConfigRepository();
            const response = await userConfigRepository.search(buildUserTableSettingsCriteria(key), Shopware.Context.api);
            const userTableSetting = getFirstUserTableSetting(response);

            if (!userTableSetting) {
                return;
            }

            currentUserTableSetting.value = userTableSetting;
            applyUserTableSettings(userTableSetting.value);
        } catch {
            currentUserTableSetting.value = null;
        }
    }

    async function saveUserTableSettings(): Promise<void> {
        const key = getUserTableSettingsKey();

        if (!key || !hasUserConfigPermission('user_config:create') || !hasUserConfigPermission('user_config:update')) {
            return;
        }

        const userConfigRepository = getUserConfigRepository();
        const userTableSetting = currentUserTableSetting.value ?? userConfigRepository.create(Shopware.Context.api);

        Object.assign(userTableSetting, {
            key,
            userId: getCurrentUserId(),
            value: buildUserTableSettingsValue(),
        });

        currentUserTableSetting.value = userTableSetting;

        await userConfigRepository.save(userTableSetting, Shopware.Context.api);
    }

    function saveUserTableSettingsSilently(): void {
        if (isApplyingUserTableSettings) {
            return;
        }

        void saveUserTableSettings().catch(() => {});
    }

    function buildUserTableSettingsValue(): SwMeteorEntityDataTableUserSettings {
        return {
            columns: serializeUserTableSettingColumns(),
            showOutlines: showOutlines.value,
            showStripes: showStripes.value,
            enableOutlineFraming: enableOutlineFraming.value,
            enableRowNumbering: enableRowNumbering.value,
        };
    }

    function serializeUserTableSettingColumns(): SwMeteorEntityDataTableUserSettingColumn[] {
        return options.resolvedColumns.value
            .map((column) => {
                return {
                    ...column,
                    ...(tableColumnChanges[column.property] ?? {}),
                };
            })
            .sort((columnA, columnB) => columnA.position - columnB.position)
            .map((column, index) => {
                const serializedColumn: SwMeteorEntityDataTableUserSettingColumn = {
                    property: column.property,
                    dataIndex: column.property,
                    position: index * 100,
                    visible: column.visible !== false,
                };

                if (typeof column.width === 'number') {
                    serializedColumn.width = column.width;
                }

                return serializedColumn;
            });
    }

    function applyUserTableSettings(rawUserSettings: unknown): void {
        const userSettings = normalizeUserTableSettings(rawUserSettings);

        if (!userSettings) {
            return;
        }

        isApplyingUserTableSettings = true;
        replaceTableColumnChanges(userSettings.columnChanges);

        if (typeof userSettings.showOutlines === 'boolean') {
            showOutlines.value = userSettings.showOutlines;
        }

        if (typeof userSettings.showStripes === 'boolean') {
            showStripes.value = userSettings.showStripes;
        }

        if (typeof userSettings.enableOutlineFraming === 'boolean') {
            enableOutlineFraming.value = userSettings.enableOutlineFraming;
        }

        if (typeof userSettings.enableRowNumbering === 'boolean') {
            enableRowNumbering.value = userSettings.enableRowNumbering;
        }

        void nextTick(() => {
            isApplyingUserTableSettings = false;
        });
    }

    function replaceTableColumnChanges(columnChanges: SwMeteorEntityDataTableColumnChanges): void {
        Object.keys(tableColumnChanges).forEach((property) => {
            delete tableColumnChanges[property];
        });

        Object.entries(columnChanges).forEach(
            ([
                property,
                columnChange,
            ]) => {
                tableColumnChanges[property] = columnChange;
            },
        );
    }

    function normalizeUserTableSettings(rawUserSettings: unknown): SwMeteorEntityDataTableNormalizedUserSettings | null {
        if (Array.isArray(rawUserSettings)) {
            return {
                columnChanges: normalizeUserTableSettingColumns(rawUserSettings),
            };
        }

        if (!isRecord(rawUserSettings)) {
            return null;
        }

        const userSettings = rawUserSettings as SwMeteorEntityDataTableUserSettings;
        const rawColumnChanges = userSettings.columnChanges ?? userSettings.columns;
        const columnChanges = Array.isArray(rawColumnChanges)
            ? normalizeUserTableSettingColumns(rawColumnChanges)
            : normalizeUserTableSettingColumnChanges(rawColumnChanges);

        return {
            columnChanges,
            showOutlines: userSettings.showOutlines,
            showStripes: userSettings.showStripes,
            enableOutlineFraming: userSettings.enableOutlineFraming,
            enableRowNumbering: userSettings.enableRowNumbering,
        };
    }

    function normalizeUserTableSettingColumns(rawColumns: unknown[]): SwMeteorEntityDataTableColumnChanges {
        const currentColumns = options.resolvedColumns.value;
        const currentColumnProperties = new Set(currentColumns.map((column) => column.property));
        const savedColumnSettings = new Map<string, SwMeteorEntityDataTableUserSettingColumn>();
        const savedColumnOrder: string[] = [];

        rawColumns.forEach((rawColumn) => {
            if (!isRecord(rawColumn)) {
                return;
            }

            const property = getUserTableSettingColumnProperty(rawColumn);

            if (!property || !currentColumnProperties.has(property) || savedColumnSettings.has(property)) {
                return;
            }

            savedColumnSettings.set(property, rawColumn as SwMeteorEntityDataTableUserSettingColumn);
            savedColumnOrder.push(property);
        });

        const orderedProperties = [
            ...savedColumnOrder,
            ...currentColumns.map((column) => column.property).filter((property) => !savedColumnSettings.has(property)),
        ];

        return orderedProperties.reduce<SwMeteorEntityDataTableColumnChanges>((changes, property, index) => {
            const savedColumnSetting = savedColumnSettings.get(property);
            const columnChange: SwMeteorEntityDataTableColumnChange = {
                position: index * 100,
            };

            if (typeof savedColumnSetting?.width === 'number') {
                columnChange.width = savedColumnSetting.width;
            }

            if (typeof savedColumnSetting?.visible === 'boolean') {
                columnChange.visible = savedColumnSetting.visible;
            }

            changes[property] = columnChange;

            return changes;
        }, {});
    }

    function normalizeUserTableSettingColumnChanges(rawColumnChanges: unknown): SwMeteorEntityDataTableColumnChanges {
        if (!isRecord(rawColumnChanges)) {
            return {};
        }

        const currentColumnProperties = new Set(options.resolvedColumns.value.map((column) => column.property));

        return Object.entries(rawColumnChanges).reduce<SwMeteorEntityDataTableColumnChanges>(
            (
                changes,
                [
                    property,
                    rawColumnChange,
                ],
            ) => {
                if (!currentColumnProperties.has(property) || !isRecord(rawColumnChange)) {
                    return changes;
                }

                const columnChange: SwMeteorEntityDataTableColumnChange = {};

                if (typeof rawColumnChange.position === 'number') {
                    columnChange.position = rawColumnChange.position;
                }

                if (typeof rawColumnChange.width === 'number') {
                    columnChange.width = rawColumnChange.width;
                }

                if (typeof rawColumnChange.visible === 'boolean') {
                    columnChange.visible = rawColumnChange.visible;
                }

                if (Object.keys(columnChange).length > 0) {
                    changes[property] = columnChange;
                }

                return changes;
            },
            {},
        );
    }

    function getUserTableSettingColumnProperty(column: Record<string, unknown>): string | null {
        if (typeof column.property === 'string') {
            return column.property;
        }

        if (typeof column.dataIndex === 'string') {
            return column.dataIndex;
        }

        return null;
    }

    function setShowOutlines(value: boolean): void {
        showOutlines.value = value;
        saveUserTableSettingsSilently();
    }

    function setShowStripes(value: boolean): void {
        showStripes.value = value;
        saveUserTableSettingsSilently();
    }

    function setEnableOutlineFraming(value: boolean): void {
        enableOutlineFraming.value = value;
        saveUserTableSettingsSilently();
    }

    function setEnableRowNumbering(value: boolean): void {
        enableRowNumbering.value = value;
        saveUserTableSettingsSilently();
    }

    watch(
        tableColumnChanges,
        () => {
            saveUserTableSettingsSilently();
        },
        {
            deep: true,
        },
    );

    return {
        tableColumnChanges,
        showOutlines,
        showStripes,
        enableOutlineFraming,
        enableRowNumbering,
        loadUserTableSettings,
        setShowOutlines,
        setShowStripes,
        setEnableOutlineFraming,
        setEnableRowNumbering,
    };
}
