/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-role-view-detailed.html.twig';
import { aclService } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [aclService],

    props: {
        role: {
            type: Object,
            required: false,
            default: null,
        },
        detailedPrivileges: {
            type: Array,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
};
