import template from './sw-import-export.html.twig';
import './sw-import-export.scss';

/**
 * @private
 */
Shopware.Component.register('sw-import-export', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {};
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        onChangeLanguage() {
            if (this.$refs.tabContent.reloadContent) {
                this.$refs.tabContent.reloadContent();
            }
        },
    },
});
