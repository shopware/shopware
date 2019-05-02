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
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            if (this.$route.params.id) {
                this.country = this.countryRepository.create(this.context, this.$route.params.id);
                this.countryStateRepository = this.repositoryFactory.create(
                    this.country.states.entity,
                    this.country.states.source
                );
            }
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.country.detail', params: { id: this.country.id } });
            });
        }
    }
});
