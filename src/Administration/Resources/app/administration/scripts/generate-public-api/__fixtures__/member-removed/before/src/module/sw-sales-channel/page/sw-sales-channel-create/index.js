/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-create.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        allowSaving() {
            return this.acl.can('sales_channel.creator');
        },
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.typeId) {
                return;
            }

            this.salesChannel = this.salesChannelRepository.create();
            this.salesChannel.typeId = this.$route.params.typeId;
            this.salesChannel.active = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.sales.channel.detail',
                params: { id: this.salesChannel.id },
            });
        },
    },
};
