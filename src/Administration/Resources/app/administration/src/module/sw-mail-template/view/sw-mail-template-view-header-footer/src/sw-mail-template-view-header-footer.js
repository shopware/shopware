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
        getList() {
            this.$refs.mailHeaderFooterList?.getList();
        },
    },
};
