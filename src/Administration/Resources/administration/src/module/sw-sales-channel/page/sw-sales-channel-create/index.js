import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-sales-channel-create.html.twig';

Component.extend('sw-sales-channel-create', 'sw-sales-channel-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.sales.channel.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.typeId) {
                return;
            }

            this.salesChannel = this.salesChannelStore.create(this.$route.params.id);
            this.salesChannel.typeId = this.$route.params.typeId;
            this.$super.createdComponent();
        },

        onSave() {
            this.salesChannel.save().then((salesChannel) => {
                this.$router.push({ name: 'sw.sales.channel.detail', params: { id: salesChannel.id } });
            });
        }
    }
});
