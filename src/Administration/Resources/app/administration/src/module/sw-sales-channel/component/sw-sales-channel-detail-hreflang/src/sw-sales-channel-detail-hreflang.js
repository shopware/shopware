/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-hreflang.html.twig';
import { Criteria } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    props: {
        salesChannel: {
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        domainCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannel.id));

            return criteria;
        },
    },
};
