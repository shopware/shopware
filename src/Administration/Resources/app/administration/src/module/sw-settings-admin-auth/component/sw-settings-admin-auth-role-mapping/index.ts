import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-settings-admin-auth-role-mapping.html.twig';
import './sw-settings-admin-auth-role-mapping.scss';

const { createId } = Shopware.Utils;

interface RoleOption {
    value: string;
    label: string;
}

interface MappingRow {
    id: string;
    group: string;
    roles: string[];
}

/**
 * Editor for the IdP group → ACL role mapping of an OIDC provider.
 *
 * The mapping object maps an IdP group name to a list of acl_role NAMES (not ids) —
 * exactly the shape the backend's SsoRoleSynchronizer consumes. The reserved 'admin'
 * pseudo-role toggles the `user.admin` flag instead of an acl_role assignment.
 *
 * @sw-package framework
 * @private
 */
export default defineComponent({
    template,

    props: {
        /**
         * The role mapping: IdP group name => list of acl_role names.
         */
        mapping: {
            type: Object as PropType<Record<string, string[]>>,
            required: false,
            default: () => ({}),
        },
        /**
         * Selectable role names, including the 'admin' pseudo-role.
         */
        roleOptions: {
            type: Array as PropType<RoleOption[]>,
            required: false,
            default: () => [],
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['update:mapping'],

    data() {
        return {
            rows: [] as MappingRow[],
        };
    },

    watch: {
        mapping(newMapping: Record<string, string[]>) {
            // Only rebuild the rows when the change came from outside (e.g. a reload after
            // save) — rebuilding on our own emits would drop empty in-progress rows.
            if (JSON.stringify(newMapping) === JSON.stringify(this.buildMapping())) {
                return;
            }

            this.rows = this.buildRows(newMapping);
        },
    },

    created() {
        this.rows = this.buildRows(this.mapping);
    },

    methods: {
        buildRows(mapping: Record<string, string[]>): MappingRow[] {
            return Object.entries(mapping ?? {}).map(
                ([
                    group,
                    roles,
                ]) => ({
                    id: createId(),
                    group,
                    roles: Array.isArray(roles) ? [...roles] : [],
                }),
            );
        },

        /**
         * Assembles the emitted mapping object. Rows without a group name are kept in the
         * editor but excluded from the mapping until they are complete.
         */
        buildMapping(): Record<string, string[]> {
            const mapping: Record<string, string[]> = {};

            this.rows.forEach((row) => {
                const group = row.group.trim();
                if (group === '') {
                    return;
                }

                mapping[group] = [...row.roles];
            });

            return mapping;
        },

        emitMapping() {
            this.$emit('update:mapping', this.buildMapping());
        },

        onAddRow() {
            this.rows.push({
                id: createId(),
                group: '',
                roles: [],
            });
        },

        onRemoveRow(id: string) {
            this.rows = this.rows.filter((row) => row.id !== id);
            this.emitMapping();
        },

        onGroupChange(row: MappingRow, group: string) {
            row.group = group;
            this.emitMapping();
        },

        onRolesChange(row: MappingRow, roles: string[]) {
            row.roles = Array.isArray(roles) ? roles : [];
            this.emitMapping();
        },
    },
});
