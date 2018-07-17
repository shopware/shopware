import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-catalog-create.html.twig';

Component.extend('sw-catalog-create', 'sw-catalog-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.catalog.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.catalogStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then((catalog) => {
                this.$router.push({ name: 'sw.catalog.detail', params: { id: catalog.id } });
            });
        }
    }
});
