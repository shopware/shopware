/**
 * @sw-package after-sales
 */
import template from './sw-mail-template-view-header-footer.html.twig';

/**
 * @private
 */
export default {
    template,

    methods: {
        onLanguageChange() {
            this.$refs.mailHeaderFooterList?.getList();
        },
    },
};
