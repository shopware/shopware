import template from './sw-import-export-profile-list.html.twig';
import './sw-import-export-profile-list.scss';
import Criteria from '../../../../core/data-new/criteria.data';

const { Component, Mixin } = Shopware;

Component.register('sw-import-export-profile-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            repository: null,
            importExportProfiles: null,
            showDeleteModal: null,
            isLoading: false,
            total: null
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.repository = this.repositoryFactory.create('import_export_profile');

            return this.repository
                .search(new Criteria(), Shopware.Context.api)
                .then((result) => {
                    this.importExportProfiles = result;
                    this.isLoading = false;
                });
        },

        onEdit(importExportProfile) {
            if (importExportProfile && importExportProfile.id) {
                this.$router.push({
                    name: 'sw.import.export.profile_detail',
                    params: {
                        id: importExportProfile.id
                    }
                });
            }
        },

        onDeleteImportExportProfile(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.repository.delete(id, Shopware.Context.api).then(() => {
                this.createdComponent();
            });
        },

        formatFileType(mimeType) {
            const parts = mimeType.split('/');
            return parts.length > 1 ? parts[1].toUpperCase() : mimeType;
        },

        translateEntity(name) {
            return this.$tc(`global.entities.${name}`);
        },

        getColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: this.$tc('sw-import-export-profile.list.columnName'),
                routerLink: 'sw.import.export.profile_detail',
                allowResize: true,
                primary: true
            }, {
                property: 'sourceEntity',
                dataIndex: 'sourceEntity',
                label: this.$tc('sw-import-export-profile.list.columnSourceEntity'),
                allowResize: true
            }, {
                property: 'fileType',
                dataIndex: 'fileType',
                label: this.$tc('sw-import-export-profile.list.columnFileType'),
                allowResize: true
            }, {
                property: 'systemDefault',
                dataIndex: 'systemDefault',
                label: this.$tc('sw-import-export-profile.list.columnSystemDefault'),
                allowResize: true
            }, {
                property: 'updatedAt',
                dataIndex: 'updatedAt',
                label: this.$tc('sw-import-export-profile.list.columnUpdatedAt'),
                allowResize: true
            }, {
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$tc('sw-import-export-profile.list.columnCreatedAt'),
                allowResize: true
            }];
        }
    }
});
