import template from './sw-dashboard-external-link.html.twig';
import './sw-dashboard-external-link.scss';

/**
 * @package merchant-services
 *
 * @deprecated tag:v6.5.0 - Use sw-external-link instead
 * @status deprecated
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    deprecated: '6.5.0',
    template,
    props: {
        title: {
            type: String,
            required: true,
        },

        link: {
            type: String,
            required: true,
        },
    },
};
