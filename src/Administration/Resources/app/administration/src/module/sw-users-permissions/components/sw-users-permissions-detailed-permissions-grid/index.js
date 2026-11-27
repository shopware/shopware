/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-detailed-permissions-grid.html.twig';
import './sw-users-permissions-detailed-permissions-grid.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['privileges'],

    props: {
        role: {
            type: Object,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        detailedPrivileges: {
            type: Array,
            required: true,
        },
    },

    computed: {
        allEntities() {
            const entitiesMap = Shopware.Application.getContainer('factory').entityDefinition.getDefinitionRegistry();

            return [...entitiesMap.keys()];
        },

        allGeneralSelectedPrivileges() {
            return this.privileges.getPrivilegesForAdminPrivilegeKeys(this.role.privileges);
        },

        permissionTypes() {
            return [
                'read',
                'update',
                'create',
                'delete',
            ];
        },
    },

    methods: {
        isEntitySelected(entity, role) {
            const identifier = `${entity}:${role}`;

            const allPrivileges = [...this.allGeneralSelectedPrivileges, ...this.detailedPrivileges];

            return allPrivileges.includes(identifier);
        },

        isEntityDisabled(entity, role) {
            if (this.disabled) {
                return true;
            }

            const identifier = `${entity}:${role}`;

            return this.allGeneralSelectedPrivileges.includes(identifier);
        },

        /**
         * Names the General tab selections granting the privilege, with the lowest granting role per module,
         * for example "Granted via: Countries (View), Tags (View)".
         */
        coveredByGeneralTooltip(entity, role) {
            const sourceKeys = this.privileges.getSourceKeysForPrivilege(`${entity}:${role}`, this.role.privileges);

            if (sourceKeys.length === 0) {
                return this.$t('sw-users-permissions.roles.detailed-grid.coveredByGeneralMessage');
            }

            const mappings = this.privileges.getPrivilegesMappings();
            const sources = [];

            sourceKeys.forEach((sourceKey) => {
                const [key, sourceRole] = sourceKey.split('.');
                const mapping = mappings.find((privilegeMapping) => {
                    return privilegeMapping.key === key && sourceRole in privilegeMapping.roles;
                });

                if (!mapping) {
                    return;
                }

                const sourceId = `${mapping.category}.${key}`;
                const roleOrder = Object.keys(mapping.roles);
                const existing = sources.find((source) => source.id === sourceId);

                if (existing && roleOrder.indexOf(existing.role) <= roleOrder.indexOf(sourceRole)) {
                    return;
                }

                const source = existing ?? { id: sourceId, mapping, key };
                source.role = sourceRole;

                if (!existing) {
                    sources.push(source);
                }
            });

            const labels = sources.map((source) => {
                if (source.mapping.category === 'additional_permissions') {
                    return this.$t(`sw-privileges.additional_permissions.${source.key}.${source.role}`);
                }

                return `${this.$t(`sw-privileges.permissions.${source.key}.label`)} (${this.$t(
                    `sw-privileges.roles.${source.role}`,
                )})`;
            });

            return this.$t('sw-users-permissions.roles.detailed-grid.grantedVia', {
                sources: labels.join(', '),
            });
        },

        changePermissionForEntity(entity, role) {
            const identifier = `${entity}:${role}`;

            const privilegeIndex = this.detailedPrivileges.indexOf(identifier);

            if (privilegeIndex >= 0) {
                this.detailedPrivileges.splice(privilegeIndex, 1);
                return;
            }

            this.detailedPrivileges.push(identifier);
        },
    },
};
