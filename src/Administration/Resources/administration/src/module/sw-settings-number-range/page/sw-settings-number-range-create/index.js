import template from './sw-settings-number-range-create.html.twig';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-settings-number-range-create', 'sw-settings-number-range-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.number.range.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            if (this.$route.params.id) {
                this.numberRange = this.numberRangeStore.create(this.$route.params.id);
            } else {
                this.numberRange = this.numberRangeStore.create();
            }
            this.numberRange.start = 1;
            this.numberRange.global = false;
            this.numberRange.isLoading = true;
            this.$super('createdComponent');
            this.getPreview();
            this.splitPattern();
            this.numberRange.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.number.range.detail', params: { id: this.numberRange.id } });
        },

        onSave() {
            this.$super('onSave');
        }
    }
});
