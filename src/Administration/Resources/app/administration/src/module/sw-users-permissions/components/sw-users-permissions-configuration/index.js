/**
 * @package system-settings
 */
import template from './sw-users-permissions-configuration.html.twig';
import './sw-users-permissions-configuration.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    methods: {
        onChangeLoading(loading) {
            this.$emit('loading-change', loading);
        },
    },
};
