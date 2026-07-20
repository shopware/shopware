/**
 * @sw-package after-sales
 */
import template from './sw-mail-template-view-templates.html.twig';

/**
 * @private
 */
export default {
    template,

    methods: {
        getList() {
            this.$refs.mailTemplateList?.getList();
        },
    },
};
