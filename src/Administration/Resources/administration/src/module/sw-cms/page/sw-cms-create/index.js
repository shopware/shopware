import template from './sw-cms-create.html.twig';

const { Component, Mixin, State } = Shopware;
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
            return State.getStore('language');
        },

        pageHasSections() {
            return this.page.sections.length > 0 && this.wizardComplete;
        }
    },

    methods: {
        createdComponent() {
            this.$store.commit('adminMenu/collapseSidebar');

            if (!this.isSystemDefaultLanguage) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            this.page = this.pageRepository.create();
            this.page.sections = [];
        },

        onSave() {
            this.savePage();
            this.$router.push({ name: 'sw.cms.detail', params: { id: this.page.id } });
        },

        savePage() {
            this.isSaveSuccessful = false;

            if ((this.isSystemDefaultLanguage && !this.page.name) || !this.page.type) {
                this.pageConfigOpen();

                const warningTitle = this.$tc('sw-cms.detail.notificationTitleMissingFields');
                const warningMessage = this.$tc('sw-cms.detail.notificationMessageMissingFields');
                this.createNotificationWarning({
                    title: warningTitle,
                    message: warningMessage
                });

                return Promise.reject();
            }

            this.deleteEntityAndRequiredConfigKey(this.page.sections);

            this.isLoading = true;

            return this.pageRepository.save(this.page, this.apiContext).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((exception) => {
                this.isLoading = false;

                const errorNotificationTitle = this.$tc('sw-cms.detail.notificationTitlePageError');
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
