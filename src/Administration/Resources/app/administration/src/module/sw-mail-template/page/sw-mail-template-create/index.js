import template from './sw-mail-template-create.html.twig';

const utils = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
                this.mailTemplate = this.mailTemplateRepository.create();
            }

            this.mailTemplateId = this.mailTemplate.id;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.mail.template.detail', params: { id: this.mailTemplate.id } });
        },

        onSave() {
            this.$super('onSave');
        },
    },
};
