import template from './sw-extension-permissions-modal.html.twig';
import './sw-extension-permissions-modal.scss';

/**
 * @package merchant-services
 * @private
 */
export default {
    template,

    props: {
        permissions: {
            type: Object,
            required: true,
        },
        domains: {
            type: Array,
            required: false,
            default: () => [],
        },
        extensionLabel: {
            type: String,
            required: true,
        },
        actionLabel: {
            type: String,
            required: false,
            default: null,
        },
        closeLabel: {
            type: String,
            required: false,
            default: null,
        },
        title: {
            type: String,
            required: false,
            default: null,
        },
        description: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            showDetailsModal: false,
            showDomainsModal: false,
            selectedEntity: '',
        };
    },

    computed: {
        modalTitle() {
            if (this.title) {
                return this.title;
            }

            return this.$tc(
                'sw-extension-store.component.sw-extension-permissions-modal.title',
                1,
                { extensionLabel: this.extensionLabel },
            );
        },

        permissionsWithGroupedOperations() {
            return Object.fromEntries(Object.entries(this.permissions)
                .map(([category, permissions]) => {
                    permissions = permissions.reduce((acc, permission) => {
                        const entity = permission.entity;

                        if (entity === 'additional_privileges') {
                            acc[permission.operation] = [];

                            return acc;
                        }

                        acc[entity] = (acc[entity] || []).concat(permission.operation);

                        return acc;
                    }, {});
                    return [category, permissions];
                }));
        },

        domainsList() {
            if (this.domains && Array.isArray(this.domains)) {
                return this.domains;
            }

            return [];
        },

        closeBtnLabel() {
            if (this.closeLabel) {
                return this.closeLabel;
            }

            return this.$tc('global.sw-modal.labelClose');
        },

        descriptionText() {
            if (this.description) {
                return this.description;
            }

            return this.$tc(
                'sw-extension-store.component.sw-extension-permissions-modal.description',
                1,
                { extensionLabel: this.extensionLabel },
            );
        },
    },

    methods: {
        close() {
            this.$emit('modal-close');
        },

        closeWithAction() {
            this.$emit('close-with-action');
        },

        categoryLabel(category) {
            return this.$tc(`entityCategories.${category}.title`);
        },

        openDetailsModal(category) {
            this.selectedEntity = category;
            this.showDetailsModal = true;
        },

        closeDetailsModal() {
            this.selectedEntity = '';
            this.showDetailsModal = false;
        },

        toggleDomainsModal(shouldOpen) {
            this.showDomainsModal = !!shouldOpen;
        },
    },
};
