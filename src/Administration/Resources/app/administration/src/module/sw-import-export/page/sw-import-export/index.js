/**
 * @package system-settings
 */
import template from './sw-import-export.html.twig';
import './sw-import-export.scss';

/**
 * @private
 */
export default {
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
};
