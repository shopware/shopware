/**
 * @sw-package fundamentals@after-sales
 */
import template from './sw-import-export.html.twig';
import './sw-import-export.scss';

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
