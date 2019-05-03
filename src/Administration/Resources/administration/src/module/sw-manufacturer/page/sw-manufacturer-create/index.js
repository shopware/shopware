import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-manufacturer-create.html.twig';

Component.extend('sw-manufacturer-create', 'sw-manufacturer-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.manufacturer.create') && !to.params.id) {
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
                this.manufacturer = this.manufacturerStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.manufacturer.detail', params: { id: this.manufacturer.id } });
        },

        onSave() {
            this.$super.onSave();
        }
    }
});
