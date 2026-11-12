/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-create.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    methods: {
        createdComponent() {
            this.salesChannel = this.salesChannelRepository.create();
            this.salesChannel.typeId = this.$route.params.typeId;
        },
    },
};
