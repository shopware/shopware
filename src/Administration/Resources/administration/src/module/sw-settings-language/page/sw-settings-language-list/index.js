import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-language-list.html.twig';

Component.register('sw-settings-language-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'language',
            sortBy: 'language.name'
        };
    },

    methods: {
        isDefault(id) {
            const defaultLanguageIds = ['20080911ffff4fffafffffff19830531', '00e84bd18c574a6ca748ac0db17654dc'];
            return defaultLanguageIds.includes(id);
        },

        getItemParent(item) {
            if (item.parentId === null) {
                return { name: '' };
            }

            return this.store.getById(item.parentId);
        }
    }
});
