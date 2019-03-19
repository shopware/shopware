import { Component, State } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-attribute-detail-base.html.twig';
import './sw-attribute-set-detail-base.scss';

Component.register('sw-attribute-set-detail-base', {
    template,

    inject: ['attributeDataProviderService'],

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
                label: this.$tc('sw-settings-attribute.attribute.detail.labelLabel')
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
            return State.getStore('locale');
        },
        attributeSetRelationStore() {
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
            const entityNames = this.attributeDataProviderService.getEntityNames();
            const entityNameStoreEntities = [];

            entityNames.forEach((name) => {
                const entity = this.attributeSetRelationStore.create();
                this.attributeSetRelationStore.removeById(entity.id);
                entity.entityName = name;
                const searchField = { name: name };
                Object.keys(this.$root.$i18n.messages).forEach(locale => {
                    if (this.$te(`global.entities.${name}`)) {
                        searchField[locale] = this.$tc(`global.entities.${name}`, 2, locale);
                    }
                });
                entity.meta.viewData.searchField = searchField;
                entity.meta.viewData.entityName = name;
                entityNameStoreEntities.push(entity);
            });

            this.entityNameStore = new LocalStore(entityNameStoreEntities, 'id', 'searchField');
        }
    }
});
