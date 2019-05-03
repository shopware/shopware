import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-currency-create.html.twig';


Component.extend('sw-settings-currency-create', 'sw-settings-currency-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.currency.create') && !to.params.id) {
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
                this.currencyStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.currency.detail', params: { id: this.currency.id } });
        },

        onSave() {
            this.$super.onSave();
        }
    }
});
