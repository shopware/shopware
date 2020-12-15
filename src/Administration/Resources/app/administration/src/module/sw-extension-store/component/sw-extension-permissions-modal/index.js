import template from './sw-extension-permissions-modal.html.twig';
import './sw-extension-permissions-modal.scss';

const { Component } = Shopware;

Component.register('sw-extension-permissions-modal', {
    template,

    props: {
        permissions: {
            type: Object,
            required: true
        },
        extensionLabel: {
            type: String,
            required: true
        },
        actionLabel: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            showDetailsModal: false,
            selectedEntity: ''
        };
    },

    computed: {
        modalTitle() {
            return this.$t('sw-extension-store.component.sw-extension-permissions-modal.title', { extensionLabel: this.extensionLabel });
        },

        permissionsWithGroupedOperations() {
            return Object.fromEntries(Object.entries(this.permissions)
                .map(([category, permissions]) => {
                    permissions = permissions.reduce((acc, permission) => {
                        const entity = permission.entity;
                        acc[entity] = (acc[entity] || []).concat(permission.operation);

                        return acc;
                    }, {});
                    return [category, permissions];
                }));
        }
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
        }
    }
});
