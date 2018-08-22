import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-settings-tax-create', 'sw-settings-tax-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.tax.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.taxStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.tax.detail', params: { id: this.tax.id } });
            });
        }
    }
});
