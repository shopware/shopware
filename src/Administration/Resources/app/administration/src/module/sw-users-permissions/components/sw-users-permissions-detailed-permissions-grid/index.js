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
            return [
                ...new Set([
                    ...this.privileges.getPrivilegesForAdminPrivilegeKeys(this.role.privileges),
                    ...this.privileges.getDefaultUserPrivileges(),
                ]),
            ];
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

            const allPrivileges = [
                ...this.allGeneralSelectedPrivileges,
                ...this.detailedPrivileges,
            ];

            return allPrivileges.includes(identifier);
        },

        isEntityDisabled(entity, role) {
            if (this.disabled) {
                return true;
            }

            const identifier = `${entity}:${role}`;

            return this.allGeneralSelectedPrivileges.includes(identifier);
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
