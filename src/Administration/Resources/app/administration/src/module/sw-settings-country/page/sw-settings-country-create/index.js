import template from './sw-settings-country-create.html.twig';

const { Component, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

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
            return StateDeprecated.getStore('language');
        }
    },

    methods: {
        createdComponent() {
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            if (this.$route.params.id) {
                this.country = this.countryRepository.create(Shopware.Context.api, this.$route.params.id);
                this.countryStateRepository = this.repositoryFactory.create(
                    this.country.states.entity,
                    this.country.states.source
                );
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.country.detail', params: { id: this.country.id } });
        },

        onSave() {
            this.$super('onSave');
        }
    }
});
