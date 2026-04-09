/**
 * @sw-package discovery
 */

import template from './sw-cms-el-form-revocation-request.html.twig';

/**
 * @sw-package discovery
 * @private
 */
export default {
    template,
    props: {
        formSettings: {
            type: Object,
            required: true,
            default: null,
        },
    },
};
