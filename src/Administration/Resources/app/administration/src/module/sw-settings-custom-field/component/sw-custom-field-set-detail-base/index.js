/**
 * @package system-settings
 */
import template from './sw-custom-field-set-detail-base.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['customFieldDataProviderService', 'acl'],

    props: {
        set: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },
        technicalNameError: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
            },
        };
    },

    computed: {
        locales() {
            if (this.set.config.translated && this.set.config.translated === true) {
                return Object.keys(this.$root.$i18n.messages);
            }

            return [this.$root.$i18n.fallbackLocale];
        },

        customFieldSetRelationRepository() {
            if (!this.set.relations) {
                return undefined;
            }

            return Shopware.Service('repositoryFactory').create(
                this.set.relations.entity,
                this.set.relations.source,
            );
        },

        selectedRelationEntityNames() {
            if (!this.set.relations) {
                return [];
            }

            return this.set.relations.map(relation => relation.entityName);
        },

        relationEntityNames() {
            if (!this.set.relations) {
                return [];
            }

            const entityNames = this.customFieldDataProviderService.getEntityNames();

            return entityNames.map(entityName => {
                const relation = this.customFieldSetRelationRepository.create();
                relation.entityName = entityName;
                this.$set(relation, 'searchField', {});

                Object.keys(this.$root.$i18n.messages).forEach(locale => {
                    if (!this.$te(`global.entities.${entityName}`)) {
                        return;
                    }

                    this.$set(
                        relation.searchField,
                        locale,
                        this.$tc(`global.entities.${entityName}`, 2, locale),
                    );
                });

                return relation;
            });
        },
    },

    methods: {

        onAddRelation(relation) {
            this.set.relations.push(relation);
        },

        onRemoveRelation(relationToRemove) {
            const matchingRelation = this.set.relations.find(relation => {
                return relation.entityName === relationToRemove.entityName;
            });

            if (!matchingRelation) {
                return;
            }

            this.set.relations.remove(matchingRelation.id);
        },

        searchRelationEntityNames({ options, searchTerm }) {
            const lowerSearchTerm = searchTerm.toLowerCase();

            return options.filter(option => {
                return Object.values(option.searchField).some(label => {
                    return label.toLowerCase().includes(lowerSearchTerm);
                });
            });
        },

        onTechnicalNameChange() {
            this.$emit('reset-errors');
        },
    },
};
