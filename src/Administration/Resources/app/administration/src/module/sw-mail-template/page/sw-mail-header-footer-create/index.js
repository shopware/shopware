import template from './sw-mail-header-footer-create.html.twig';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-mail-header-footer-create', 'sw-mail-header-footer-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.mail.template.create_head_foot') && !to.params.id) {
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
                this.mailHeaderFooter = this.mailHeaderFooterRepository.create(Shopware.Context.api, this.$route.params.id);
            }

            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.mail.template.detail_head_foot', params: { id: this.$route.params.id } });
        },

        onSave() {
            this.$super('onSave');
        },
    },
});
