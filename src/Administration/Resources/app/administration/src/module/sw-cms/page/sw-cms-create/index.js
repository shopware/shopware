import template from './sw-cms-create.html.twig';

const { Component, Mixin, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-cms-create', 'sw-cms-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            wizardComplete: false
        };
    },

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.cms.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    computed: {
        languageStore() {
            return StateDeprecated.getStore('language');
        },

        pageHasSections() {
            return this.page.sections.length > 0 && this.wizardComplete;
        }
    },

    methods: {
        createdComponent() {
            Shopware.State.commit('adminMenu/collapseSidebar');

            const isSystemDefaultLanguage = Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId;
            if (!isSystemDefaultLanguage) {
                this.languageStore.setCurrentId(Shopware.Context.api.systemLanguageId);
                this.$store.commit('cmsPageState/setIsSystemDefaultLanguage', isSystemDefaultLanguage);
            }

            this.page = this.pageRepository.create();
            this.page.sections = [];
        },

        onSave() {
            this.isSaveSuccessful = false;

            if ((this.isSystemDefaultLanguage && !this.page.name) || !this.page.type) {
                const warningTitle = this.$tc('sw-cms.detail.notification.titleMissingFields');
                const warningMessage = this.$tc('sw-cms.detail.notification.messageMissingFields');
                this.createNotificationWarning({
                    title: warningTitle,
                    message: warningMessage
                });

                return Promise.reject();
            }

            this.deleteEntityAndRequiredConfigKey(this.page.sections);

            this.isLoading = true;

            return this.pageRepository.save(this.page, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                this.$router.push({ name: 'sw.cms.detail', params: { id: this.page.id } });
            }).catch((exception) => {
                this.isLoading = false;

                const errorNotificationTitle = this.$tc('sw-cms.detail.notification.titlePageError');
                this.createNotificationError({
                    title: errorNotificationTitle,
                    message: exception.message
                });

                return Promise.reject(exception);
            });
        },

        onWizardComplete() {
            if (this.page.type === 'product_list') {
                this.onPageTypeChange();
            }

            this.wizardComplete = true;
            this.onSave();
        }
    }
});
