/**
 * @package system-settings
 */
import template from './sw-users-permissions.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        reloadUserListing() {
            if (this.$refs.userListing) {
                this.$refs.userListing.getList();
            }

            if (this.$refs.roleListing) {
                this.$refs.roleListing.getList();
            }
        },
    },
};
