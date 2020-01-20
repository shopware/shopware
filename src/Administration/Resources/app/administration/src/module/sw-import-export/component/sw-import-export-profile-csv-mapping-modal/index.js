import template from './sw-import-export-profile-csv-mapping-modal.html.twig';
import './sw-import-export-profile-csv-mapping-modal.scss';

const { Component, EntityDefinition } = Shopware;

Component.register('sw-import-export-profile-csv-mapping-modal', {
    template,

    props: {
        importExportProfile: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            mappingDefinitions: [
                {
                    fileField: null,
                    entityField: null,
                    valueSubstitution: null,
                    isIdentifier: false
                }
            ],
            availableEntityFields: []
        };
    },

    created() {
        this.createdComponent();

        if (this.importExportProfile.mapping) {
            this.mappingDefinitions = this.importExportProfile.mapping;
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
            this.availableEntityFields = this.buildFlattenedFieldSet();
        },

        onIdentifierChange(selectedIndex) {
            // Make sure only one field is specified as identifier
            this.mappingDefinitions.forEach((mapping, index) => {
                if (index === selectedIndex) {
                    return;
                }
                mapping.isIdentifier = false;
            });
        },


        onCloseModal() {
            this.filterMappingDefinitions();
            this.importExportProfile.mapping = this.mappingDefinitions;
            this.$emit('closeMappingModal');
        },

        filterMappingDefinitions() {
            this.mappingDefinitions = this.mappingDefinitions.filter((item) => {
                return item.fileField !== null || item.entityField !== null;
            });
        },

        buildFlattenedFieldSet() {
            const rootEntityDefinition = EntityDefinition.get(this.importExportProfile.sourceEntity);
            const flattenedFieldSet = {};

            rootEntityDefinition.forEachField((property, propertyName) => {
                // Add direct scalar fields
                if (rootEntityDefinition.isScalarField(property) && !property.flags.primary_key) {
                    flattenedFieldSet[propertyName] = property.type;
                }

                // Add scalar fields from associations
                if (property.type === 'association') {
                    try {
                        const subDefinition = EntityDefinition.get(property.entity);
                        subDefinition.forEachField((subProperty, subPropertyName) => {
                            if (subDefinition.isScalarField(subProperty)) {
                                flattenedFieldSet[`${propertyName}.${subPropertyName}`] = subProperty.type;
                            }
                        });
                    } catch {
                        // Ignore missing definitions, because translation entities are unknown to the client
                    }
                }
            });

            return flattenedFieldSet;
        },

        addMappingField() {
            this.mappingDefinitions.push({
                fileField: null,
                entityField: null,
                valueSubstitution: null,
                isIdentifier: false
            });
        },

        onConfirmDelete(item) {
            const index = this.mappingDefinitions.findIndex((selected) => {
                return selected === item;
            });

            if (index > -1) {
                this.mappingDefinitions.splice(index, 1);
            }
        },

        onSaveMapping() {
            this.filterMappingDefinitions();
            this.importExportProfile.mapping = this.mappingDefinitions;

            this.$emit('saveMapping');
        },

        getColumns() {
            return [{
                property: 'fileField',
                dataIndex: 'fileField',
                label: 'sw-import-export-profile.mapping.columnFileField',
                allowResize: true,
                primary: true
            }, {
                property: 'entityField',
                dataIndex: 'entityField',
                label: 'sw-import-export-profile.mapping.columnEntityField',
                allowResize: true
            }, {
                property: 'valueSubstitution',
                dataIndex: 'valueSubstitution',
                label: 'sw-import-export-profile.mapping.columnValueSubstitution',
                allowResize: true
            }, {
                property: 'isIdentifier',
                dataIndex: 'isIdentifier',
                label: 'sw-import-export-profile.mapping.columnIsIdentifier',
                allowResize: true
            }];
        }
    }
});
