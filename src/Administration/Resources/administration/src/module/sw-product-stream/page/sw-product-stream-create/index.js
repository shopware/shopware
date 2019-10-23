import template from './sw-product-stream-create.html.twig';

const { Component, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-product-stream-create', 'sw-product-stream-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.product.stream.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    computed: {
        languageStore() {
            return StateDeprecated.getStore('language');
        }
    },

    methods: {
        createdComponent() {
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            if (this.$route.params.id) {
                this.productStream = this.productStreamStore.create(this.$route.params.id);
            }

            this.$super('createdComponent');
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.product.stream.detail', params: { id: this.productStream.id } });
        },

        onSave() {
            this.$super('onSave');
        }
    }
});
