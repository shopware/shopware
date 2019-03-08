import { Component, State, Entity } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-seo-url-template-card.html.twig';

Component.register('sw-seo-url-template-card', {
    template,

    data() {
        return {
            seoUrls: null,
            isLoading: true,
            seoSettingsComponent: null,
            entityPropertyStores: {}
        };
    },

    computed: {
        seoUrlStore() {
            return State.getStore('seo_url_template');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.findSettingsDetailComponent();

            this.registerListener();

            this.seoUrlStore.getList({}).then((res) => {
                this.seoUrls = res.items;
                this.isLoading = false;
            });
        },
        findSettingsDetailComponent() {
            this.seoSettingsComponent = this.$parent;
            while (this.seoSettingsComponent.$options.name !== 'sw-settings-seo') {
                this.seoSettingsComponent = this.seoSettingsComponent.$parent;
            }
        },
        registerListener() {
            this.seoSettingsComponent.$on('saved', this.onSave);
        },
        getLabel(seoUrl) {
            const routeName = seoUrl.routeName.replace(/\./g, '-');
            if (this.$te(`sw-seo-url-template-card.routeNames.${routeName}`)) {
                return this.$tc(`sw-seo-url-template-card.routeNames.${routeName}`);
            }

            return seoUrl.routeName;
        },
        onSave() {
            this.seoUrlStore.sync();
        },
        getEntityPropertyStore(entityName) {
            if (!this.entityPropertyStores.hasOwnProperty(entityName)) {
                const definition = Entity.getDefinition(entityName);
                const properties = definition.properties;

                const storeOptions = [];

                Object.keys(properties).forEach((property) => {
                    storeOptions.push({ id: property, name: `${property}(${properties[property].type})` });
                });

                this.entityPropertyStores = Object.assign(
                    {},
                    this.entityPropertyStores,
                    { [entityName]: new LocalStore(storeOptions) }
                );
            }

            return this.entityPropertyStores[entityName];
        },
        onSelectInput(propertyName, seoUrl) {
            seoUrl.template = `${seoUrl.template} {{ ${seoUrl.entityName}.${propertyName} }}`;

            const selectComponent = this.$refs[`select-${seoUrl.entityName}`][0];
            selectComponent.loadSelected();
        }
    }
});
