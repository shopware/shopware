import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-attribute-set-list.html.twig';

Component.register('sw-settings-attribute-set-list', {
    template,

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'attribute_set',
            sortBy: 'config.name',
            datetime: '',
            showModal: false
        };
    },

    computed: {
        locale() {
            return this.$root.$i18n.locale;
        },
        fallbackLocale() {
            return this.$root.$i18n.fallbackLocale;
        },
        // Settings Listing mixin override
        titleSaveSuccess() {
            return this.$tc('sw-settings-attribute.set.list.titleDeleteSuccess');
        },
        // Settings Listing mixin override
        messageSaveSuccess() {
            if (this.deleteEntity) {
                return this.$tc(
                    'sw-settings-attribute.set.list.messageDeleteSuccess',
                    0,
                    { name: this.getInlineSnippet(this.deleteEntity.config.label) }
                );
            }
            return '';
        }
    }
});
