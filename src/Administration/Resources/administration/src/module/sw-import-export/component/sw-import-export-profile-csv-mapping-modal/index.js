import { Component } from 'src/core/shopware';
import template from './sw-import-export-profile-csv-mapping-modal.html.twig';
import './sw-import-export-profile-csv-mapping-modal.scss';

Component.register('sw-import-export-profile-csv-mapping-modal', {
    template,

    inject: ['entityRegistry'],

    props: {
        importExportProfile: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            selectedMapping: [
                {
                    fileField: null,
                    entityField: null,
                    valueSubstitution: null
                }
            ],
            availableEntityFields: []
        };
    },

    created() {
        this.createdComponent();

        if (this.importExportProfile.mapping) {
            this.selectedMapping = this.importExportProfile.mapping;
        }
    },

    computed: {
        columns() {
            return this.getColumns();
        },
        title() {
            const entityName = this.importExportProfile.sourceEntity;
            return this.$tc(
                'sw-import-export-profile.mapping.modal.title',
                0,
                { entity: this.$tc(`global.entities.${entityName}`) }
            );
        }
    },

    methods: {
        createdComponent() {
            this.loadMapping();
        },

        onCloseModal() {
            this.filterSelectedMapping();
            this.importExportProfile.mapping = this.selectedMapping;
            this.$emit('closeMappingModal');
        },

        filterSelectedMapping() {
            this.selectedMapping = this.selectedMapping.filter((item) => {
                return item.fileField !== null || item.entityField !== null;
            });
        },

        loadMapping() {
            this.availableEntityFields = this.entityRegistry.getProperties(this.importExportProfile.sourceEntity);
        },

        addMappingField() {
            this.selectedMapping.push({
                fileField: null,
                entityField: null,
                valueSubstitution: null
            });
        },

        onConfirmDelete(item) {
            const index = this.selectedMapping.findIndex((selected) => {
                return selected === item;
            });

            if (index > -1) {
                this.selectedMapping.splice(index, 1);
            }
        },

        onSaveMapping() {
            this.filterSelectedMapping();
            this.importExportProfile.mapping = this.selectedMapping;

            this.$emit('saveMapping');
        },

        getColumns() {
            return [{
                property: 'fileField',
                dataIndex: 'fileField',
                label: this.$tc('sw-import-export-profile.mapping.columnFileField'),
                allowResize: true,
                primary: true
            }, {
                property: 'entityField',
                dataIndex: 'entityField',
                label: this.$tc('sw-import-export-profile.mapping.columnEntityField'),
                allowResize: true
            }, {
                property: 'valueSubstitution',
                dataIndex: 'valueSubstitution',
                label: this.$tc('sw-import-export-profile.mapping.columnValueSubstitution'),
                allowResize: true
            }];
        }
    }
});
