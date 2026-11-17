/**
 * @sw-package fundamentals@framework
 */
import type { HiddenAccessGroup } from 'src/app/service/privileges.service';
import template from './sw-users-permissions-included-access-modal.html.twig';
import './sw-users-permissions-included-access-modal.scss';

type IncludedAccessArea = {
    key: string;
    label: string;
    isShared: boolean;
    entries: Array<{
        operations: string;
        entities: string;
    }>;
    entityTooltip: string;
    sources: string[];
    sourceTooltip: string;
};

type IncludedAccessTier = 'personal' | 'write' | 'read';

type IncludedAccessSection = {
    tier: IncludedAccessTier;
    icon: string;
    badgeVariant: string;
    areas: IncludedAccessArea[];
};

const ROLE_ORDER = [
    'viewer',
    'editor',
    'creator',
    'deleter',
];

const OPERATION_ORDER = [
    'read',
    'update',
    'create',
    'delete',
];

/**
 * Shows what the given admin privilege keys grant on data of other areas, ordered by how sensitive it is:
 * personal data first, then write access, then plain reads.
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
         * Role view: leaves out what the owning area's own selected roles grant and shows where the access comes from.
         */
        withSources: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        accessGroups(): HiddenAccessGroup[] {
            if (this.withSources) {
                return this.privileges.getIncludedAccess(this.privilegeKeys);
            }

            return this.privileges.getHiddenAccessForAdminPrivilegeKeys(this.privilegeKeys);
        },

        areasByTier(): Record<IncludedAccessTier, IncludedAccessArea[]> {
            const tiers: Record<IncludedAccessTier, IncludedAccessArea[]> = {
                personal: [],
                write: [],
                read: [],
            };

            this.accessGroups.forEach((group) => {
                tiers[this.tierOf(group)].push(this.toArea(group));
            });

            // Shared data belongs to no area, so it closes each list instead of sorting in between
            Object.values(tiers).forEach((areas) => {
                areas.sort((a, b) => Number(a.isShared) - Number(b.isShared) || a.label.localeCompare(b.label));
            });

            return tiers;
        },

        tableSections(): IncludedAccessSection[] {
            return [
                {
                    tier: 'personal' as const,
                    icon: 'regular-user',
                    badgeVariant: 'critical',
                    areas: this.areasByTier.personal,
                },
                {
                    tier: 'write' as const,
                    icon: 'regular-pencil-s',
                    badgeVariant: 'attention',
                    areas: this.areasByTier.write,
                },
            ].filter((section) => section.areas.length > 0);
        },

        readAreas(): IncludedAccessArea[] {
            return this.areasByTier.read;
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
            const entries = group.entries.map((entry) => ({
                operations: this.operationsLabel(entry.operations),
                entities: entry.entities.map((entity) => this.entityLabel(entity)).join(', '),
            }));
            const sources = this.withSources ? this.sourceLabels(group.privileges) : [];

            return {
                key: group.ownerKey ?? 'shared',
                label: this.areaLabel(group.ownerKey),
                isShared: group.ownerKey === null,
                entries,
                entityTooltip: group.entries
                    .flatMap((entry) => entry.entities.map((entity) => this.escapeHtml(this.entityLabel(entity))))
                    .join(',<br>'),
                sources,
                sourceTooltip: sources.map((source) => this.escapeHtml(source)).join(',<br>'),
            };
        },

        areaLabel(ownerKey: string | null): string {
            if (ownerKey === null) {
                return this.$t('sw-users-permissions.roles.included-access.sharedData');
            }

            return this.$t(`sw-privileges.permissions.${ownerKey}.label`);
        },

        operationsLabel(operations: string[]): string {
            if (OPERATION_ORDER.every((operation) => operations.includes(operation))) {
                return this.$t('sw-users-permissions.roles.included-access.fullAccess');
            }

            const label = [...operations]
                .sort((a, b) => OPERATION_ORDER.indexOf(a) - OPERATION_ORDER.indexOf(b))
                .map((operation) => this.$t(`sw-users-permissions.roles.included-access.operations.${operation}`))
                .join(', ');

            return label.charAt(0).toUpperCase() + label.slice(1);
        },

        entityLabel(entity: string): string {
            const snippetKey = `global.entities.${entity}`;
            const label = this.$te(snippetKey) ? this.$t(snippetKey, {}, 1) : entity.replace(/_/g, ' ');

            return label.charAt(0).toUpperCase() + label.slice(1);
        },

        /**
         * One source per area, with its highest selected role, for example "Orders › Edit".
         */
        sourceLabels(privileges: string[]): string[] {
            const sourceKeys = new Set(
                privileges.flatMap((privilege) => {
                    return this.privileges.getSourceKeysForPrivilege(privilege, this.privilegeKeys);
                }),
            );

            const highestRoleByKey = new Map<string, string>();
            sourceKeys.forEach((sourceKey) => {
                const [key, role] = sourceKey.split('.');
                const current = highestRoleByKey.get(key);

                if (!current || ROLE_ORDER.indexOf(role) > ROLE_ORDER.indexOf(current)) {
                    highestRoleByKey.set(key, role);
                }
            });

            return [...highestRoleByKey.entries()]
                .map(([key, role]) => this.sourceLabel(key, role))
                .sort((a, b) => a.localeCompare(b));
        },

        sourceLabel(key: string, role: string): string {
            const mapping = this.privileges.getPrivilegesMappings().find((privilegeMapping) => {
                return privilegeMapping.key === key && role in privilegeMapping.roles;
            });

            if (mapping?.category === 'additional_permissions') {
                return this.$t(`sw-privileges.additional_permissions.${key}.${role}`);
            }

            return this.$t('sw-users-permissions.roles.included-access.sourceEntry', {
                area: this.$t(`sw-privileges.permissions.${key}.label`),
                role: this.$t(`sw-privileges.roles.${role}`),
            });
        },

        /**
         * The tooltip renders HTML, so the labels are escaped before they are joined with line breaks.
         */
        escapeHtml(text: string): string {
            const element = document.createElement('span');
            element.textContent = text;

            return element.innerHTML;
        },

        /**
         * The section already says the access is view only, so the tooltip lists only where it comes from,
         * or which data it covers when there are no sources.
         */
        readAreaTooltip(area: IncludedAccessArea): string {
            return area.sources.length > 0 ? area.sourceTooltip : area.entityTooltip;
        },

        onModalChange(isOpen: boolean): void {
            if (!isOpen) {
                this.$emit('close');
            }
        },
    },
});
