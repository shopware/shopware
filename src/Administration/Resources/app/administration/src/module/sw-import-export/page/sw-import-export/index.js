/**
 * @sw-package fundamentals@after-sales
 */
import template from './sw-import-export.html.twig';

/**
 * @private
 */
export default {
    template,

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
};
