/**
 * @package buyers-experience
 */

import template from './sw-sales-channel-create.html.twig';

const utils = Shopware.Utils;

const insertIdIntoRoute = (to, from, next) => {
    if (to.name.includes('sw.sales.channel.create') && !to.params.id) {
        to.params.id = utils.createId();
    }

    next();
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    beforeRouteEnter: insertIdIntoRoute,

    beforeRouteUpdate: insertIdIntoRoute,

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

            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            this.salesChannel = this.salesChannelRepository.create();
            this.salesChannel.typeId = this.$route.params.typeId;
            this.salesChannel.active = false;

            this.$super('createdComponent');
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.sales.channel.detail', params: { id: this.salesChannel.id } });
        },

        onSave() {
            this.$super('onSave');
        },
    },
};
