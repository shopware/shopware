import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-country-create.html.twig';

Component.extend('sw-settings-country-create', 'sw-settings-country-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.country.create') && !to.params.id) {
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
            if (this.languageStore.getCurrentId() !== this.languageStore.defaultLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.defaultLanguageId);
            }

            if (this.$route.params.id) {
                this.countryStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.country.detail', params: { id: this.country.id } });
            });
        }
    }
});
