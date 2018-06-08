import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-manufacturer-create', 'sw-manufacturer-detail', {

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.manufacturer.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.manufacturerStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            const manufacturerName = this.manufacturer.name;
            const titleSaveSuccess = this.$tc('sw-manufacturer.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-manufacturer.detail.messageSaveSuccess', 0, { name: manufacturerName });

            this.manufacturer.save().then((manufacturer) => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.$router.push({ name: 'sw.manufacturer.detail', params: { id: manufacturer.id } });
            });
        }
    }
});
