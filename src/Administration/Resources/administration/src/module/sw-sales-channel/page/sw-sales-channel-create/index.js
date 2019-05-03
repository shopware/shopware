import { Component, State } from 'src/core/shopware';
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

    computed: {
        languageStore() {
            return State.getStore('language');
        }
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.typeId) {
                return;
            }

            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            this.salesChannel = this.salesChannelStore.create(this.$route.params.id);
            this.salesChannel.typeId = this.$route.params.typeId;
            this.salesChannel.active = true;

            this.$super.createdComponent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.sales.channel.detail', params: { id: this.salesChannel.id } });
        },

        onSave() {
            this.$super.onSave();
        }
    }
});
