/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-modal-detail.html.twig';
import './sw-sales-channel-modal-detail.scss';

/**
 * @public
 */
export default {
    template,

    props: {
        detailType: {
            type: Object,
            required: false,
            default: null,
        },
    },
};
