/**
 * @sw-package fundamentals@framework
 */
import type { HiddenAccessGroup } from 'src/app/service/privileges.service';
import template from './sw-users-permissions-included-access-modal.html.twig';
import './sw-users-permissions-included-access-modal.scss';

type IncludedAccessTier = 'personal' | 'write' | 'read';

/**
 * @private
 */
export type Operation = 'read' | 'update' | 'create' | 'delete';

type OperationCoverage = {
    level: 'all' | 'some' | 'none';
    entities: string[];
    sources: string[];
};

type EntityRow = {
    key: string;
    label: string;
    /**
     * The sources per operation, or `null` when the entity doesn't get the operation.
     */
    cells: Record<Operation, string[] | null>;
};

/**
 * @private
 */
export type GrantedBy = {
    key: string;
    label: string;
    roles: string;
};

/**
 * @private
 */
export type IncludedAccessArea = {
    key: string;
    label: string;
    tier: IncludedAccessTier;
    isShared: boolean;
    entityCount: number;
    coverage: Record<Operation, OperationCoverage>;
    entityRows: EntityRow[];
    grantedBy: GrantedBy[];
};

/**
 * @private
 */
export type IncludedAccessColumn = {
    operation: Operation;
    role: string;
};

type IncludedAccessGroup = {
    tier: IncludedAccessTier;
    areas: IncludedAccessArea[];
};

const TIERS: IncludedAccessTier[] = [
    'personal',
    'write',
    'read',
];

const ROLE_ORDER = [
    'viewer',
    'editor',
    'creator',
    'deleter',
];

/**
 * The matrix columns, in the order of the roles in the permissions grid.
 */
const COLUMNS: IncludedAccessColumn[] = [
    { operation: 'read', role: 'viewer' },
    { operation: 'update', role: 'editor' },
    { operation: 'create', role: 'creator' },
    { operation: 'delete', role: 'deleter' },
];

/**
 * Shows what the given admin privilege keys grant on data of other areas as a matrix of areas and operations,
 * ordered by how sensitive it is: personal data first, then changes, then plain reads.
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['privileges'],

    emits: ['close'],

    props: {
        privilegeKeys: {
            type: Array as PropType<string[]>,
            required: true,
        },

        title: {
            type: String,
            required: true,
        },

        subtitle: {
            type: String,
            required: false,
            default: '',
        },

        description: {
            type: String,
            required: false,
            default: '',
        },

        /**
         * Role view: leaves out what the owning area's own selected roles grant and lists the granting permissions.
         * The row view leaves them out, because they all belong to the row that was clicked.
         */
        withSources: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): { columns: IncludedAccessColumn[] } {
        return {
            columns: COLUMNS,
        };
    },

    computed: {
        accessGroups(): HiddenAccessGroup[] {
            if (this.withSources) {
                return this.privileges.getIncludedAccess(this.privilegeKeys);
            }

            return this.privileges.getHiddenAccessForAdminPrivilegeKeys(this.privilegeKeys);
        },

        groups(): IncludedAccessGroup[] {
            const areas = this.accessGroups.map((group) => this.toArea(group));

            return TIERS.map((tier) => ({
                tier,
                areas: areas
                    .filter((area) => area.tier === tier)
                    // Shared data belongs to no area, so it closes each list instead of sorting in between
                    .sort((a, b) => Number(a.isShared) - Number(b.isShared) || a.label.localeCompare(b.label)),
            })).filter((group) => group.areas.length > 0);
        },
    },

    methods: {
        tierOf(group: HiddenAccessGroup): IncludedAccessTier {
            if (group.containsPersonalData) {
                return 'personal';
            }

            return group.containsWrite ? 'write' : 'read';
        },

        toArea(group: HiddenAccessGroup): IncludedAccessArea {
            const entities = group.entries.flatMap((entry) => {
                return entry.entities.map((entity) => ({ entity, operations: entry.operations }));
            });
            const rolesByKey = new Map<string, Set<string>>();

            const entityRows = entities
                .map(({ entity, operations }): EntityRow => {
                    const cells = {} as EntityRow['cells'];

                    COLUMNS.forEach(({ operation }) => {
                        if (!operations.includes(operation)) {
                            cells[operation] = null;
                            return;
                        }

                        const sources = this.lowestSources(`${entity}:${operation}`);
                        sources.forEach(([key, role]) => {
                            rolesByKey.set(key, (rolesByKey.get(key) ?? new Set<string>()).add(role));
                        });

                        cells[operation] = sources
                            .map(([key, role]) => this.sourceLabel(key, role))
                            .sort((a, b) => a.localeCompare(b));
                    });

                    return { key: entity, label: this.entityLabel(entity), cells };
                })
                .sort((a, b) => {
                    return this.operationCount(b) - this.operationCount(a) || a.label.localeCompare(b.label);
                });

            const coverage = {} as Record<Operation, OperationCoverage>;
            COLUMNS.forEach(({ operation }) => {
                const rows = entityRows.filter((row) => row.cells[operation] !== null);
                let level: OperationCoverage['level'] = 'none';

                if (rows.length === entityRows.length) {
                    level = 'all';
                } else if (rows.length > 0) {
                    level = 'some';
                }

                coverage[operation] = {
                    level,
                    entities: rows.map((row) => row.label),
                    sources: [...new Set(rows.flatMap((row) => row.cells[operation] ?? []))].sort((a, b) =>
                        a.localeCompare(b),
                    ),
                };
            });

            return {
                key: group.ownerKey ?? 'shared',
                label: this.areaLabel(group.ownerKey),
                tier: this.tierOf(group),
                isShared: group.ownerKey === null,
                entityCount: entityRows.length,
                coverage,
                entityRows,
                grantedBy: this.grantedBy(rolesByKey),
            };
        },

        operationCount(row: EntityRow): number {
            return COLUMNS.filter(({ operation }) => row.cells[operation] !== null).length;
        },

        /**
         * Per area the lowest selected role that grants the privilege, which is the checkbox that brings it.
         */
        lowestSources(privilege: string): Array<[string, string]> {
            const lowestRoleByKey = new Map<string, string>();

            this.privileges.getSourceKeysForPrivilege(privilege, this.privilegeKeys).forEach((sourceKey) => {
                const [key, role] = sourceKey.split('.');
                const current = lowestRoleByKey.get(key);

                if (!current || ROLE_ORDER.indexOf(role) < ROLE_ORDER.indexOf(current)) {
                    lowestRoleByKey.set(key, role);
                }
            });

            return [...lowestRoleByKey.entries()];
        },

        /**
         * Each granting area once, with the roles that bring the access, for example "Products: View, Edit".
         */
        grantedBy(rolesByKey: Map<string, Set<string>>): GrantedBy[] {
            return [...rolesByKey.entries()]
                .map(([key, roles]) => {
                    const sortedRoles = [...roles].sort((a, b) => ROLE_ORDER.indexOf(a) - ROLE_ORDER.indexOf(b));

                    if (this.isAdditionalPermission(key)) {
                        return {
                            key,
                            label: sortedRoles.map((role) => this.sourceLabel(key, role)).join(', '),
                            roles: '',
                        };
                    }

                    return {
                        key,
                        label: this.$t(`sw-privileges.permissions.${key}.label`),
                        roles: sortedRoles.map((role) => this.$t(`sw-privileges.roles.${role}`)).join(', '),
                    };
                })
                .sort((a, b) => a.label.localeCompare(b.label));
        },

        isAdditionalPermission(key: string): boolean {
            return this.privileges.getPrivilegesMappings().some((mapping) => {
                return mapping.key === key && mapping.category === 'additional_permissions';
            });
        },

        areaLabel(ownerKey: string | null): string {
            if (ownerKey === null) {
                return this.$t('sw-users-permissions.roles.included-access.sharedData');
            }

            return this.$t(`sw-privileges.permissions.${ownerKey}.label`);
        },

        entityLabel(entity: string): string {
            const snippetKey = `global.entities.${entity}`;
            const label = this.$te(snippetKey) ? this.$t(snippetKey, {}, 1) : entity.replace(/_/g, ' ');

            return label.charAt(0).toUpperCase() + label.slice(1);
        },

        sourceLabel(key: string, role: string): string {
            if (this.isAdditionalPermission(key)) {
                return this.$t(`sw-privileges.additional_permissions.${key}.${role}`);
            }

            return this.$t('sw-users-permissions.roles.included-access.sourceEntry', {
                area: this.$t(`sw-privileges.permissions.${key}.label`),
                role: this.$t(`sw-privileges.roles.${role}`),
            });
        },

        onModalChange(isOpen: boolean): void {
            if (!isOpen) {
                this.$emit('close');
            }
        },
    },
});
