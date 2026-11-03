/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-configuration.html.twig';
import { aclService } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [aclService],

    emits: ['loading-change'],

    methods: {
        onChangeLoading(loading) {
            this.$emit('loading-change', loading);
        },
    },
};
