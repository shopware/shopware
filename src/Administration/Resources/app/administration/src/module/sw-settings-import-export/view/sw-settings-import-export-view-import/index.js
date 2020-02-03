import template from './sw-settings-import-export-view-import.html.twig';
import './sw-settings-import-export-view-import.scss';

Shopware.Component.register('sw-settings-import-export-view-import', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {};
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    }
});
