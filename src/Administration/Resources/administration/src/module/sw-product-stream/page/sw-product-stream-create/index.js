import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-product-stream-create', 'sw-product-stream-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.product.stream.create') && !to.params.id) {
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
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            if (this.$route.params.id) {
                this.productStream = this.productStreamStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.product.stream.detail', params: { id: this.productStream.id } });
            });
        }
    }
});
