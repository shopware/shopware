import template from './sw-settings-import-export.html.twig';
import './sw-settings-import-export.scss';

Shopware.Component.register('sw-settings-import-export', {
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
