import LocalStore from 'src/core/data/LocalStore';
import template from './sw-custom-field-detail-base.html.twig';

const { Component, StateDeprecated } = Shopware;

Component.register('sw-custom-field-set-detail-base', {
    template,

    inject: ['customFieldDataProviderService'],

    props: {
        set: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel')
            },
            entityNameStore: {}
        };
    },

    computed: {
        locales() {
            if (this.set.config.translated && this.set.config.translated === true) {
                return Object.keys(this.$root.$i18n.messages);
            }

            return [this.$root.$i18n.fallbackLocale];
        },
        localeStore() {
            return StateDeprecated.getStore('locale');
        },
        customFieldSetRelationStore() {
            return this.set.getAssociation('relations');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.createEntityNameStore();
        },
        createEntityNameStore() {
            const entityNames = this.customFieldDataProviderService.getEntityNames();
            const entityNameStoreEntities = [];

            entityNames.forEach((name) => {
                let entity = this.customFieldSetRelationStore.create();
                this.customFieldSetRelationStore.removeById(entity.id);
                entity.entityName = name;
                const searchField = { name: name };
                Object.keys(this.$root.$i18n.messages).forEach(locale => {
                    if (this.$te(`global.entities.${name}`)) {
                        searchField[locale] = this.$tc(`global.entities.${name}`, 2, locale);
                    }
                });

                entity = Object.assign({}, entity, { searchField: searchField });
                entityNameStoreEntities.push(entity);
            });

            this.entityNameStore = new LocalStore(entityNameStoreEntities, 'id', 'searchField');
        }
    }
});
