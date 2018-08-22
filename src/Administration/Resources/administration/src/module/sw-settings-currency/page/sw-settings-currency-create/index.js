import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-settings-currency-create', 'sw-settings-currency-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.currency.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.currencyStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.currency.detail', params: { id: this.currency.id } });
            });
        }
    }
});
