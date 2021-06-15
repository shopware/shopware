import template from './sw-extension-permissions-modal.html.twig';
import './sw-extension-permissions-modal.scss';

const { Component } = Shopware;

Component.register('sw-extension-permissions-modal', {
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
});
