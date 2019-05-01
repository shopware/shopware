import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-settings-number-range-create', 'sw-settings-number-range-detail', {

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.number.range.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.numberRange = this.numberRangeStore.create(this.$route.params.id);
            } else {
                this.numberRange = this.numberRangeStore.create();
            }
            this.numberRange.global = false;
            this.numberRange.isLoading = true;
            this.$super.createdComponent();
            this.getPreview();
            this.splitPattern();
            this.numberRange.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.number.range.detail', params: { id: this.numberRange.id } });
        },

        onSave() {
            this.$super.onSave();
        }
    }
});
