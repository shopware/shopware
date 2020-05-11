import template from './sw-mail-template-create.html.twig';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-mail-template-create', 'sw-mail-template-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.mail.template.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            if (this.$route.params.id) {
                this.mailTemplate = this.mailTemplateRepository.create(Shopware.Context.api, this.$route.params.id);
            } else {
                this.mailTemplate = this.mailTemplateRepository.create(Shopware.Context.api);
            }

            this.mailTemplateId = this.mailTemplate.id;
            this.mailTemplateSalesChannels = [];

            this.getPossibleSalesChannels([]);
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.mail.template.detail', params: { id: this.mailTemplate.id } });
        },

        onSave() {
            this.$super('onSave');
        }
    }
});
