import template from './sw-cms-create.html.twig';

const { Component, Mixin } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-cms-create', 'sw-cms-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            wizardComplete: false,
        };
    },

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.cms.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    computed: {
        pageHasSections() {
            return this.page.sections.length > 0 && this.wizardComplete;
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },
    },

    methods: {
        createdComponent() {
            Shopware.State.commit('adminMenu/collapseSidebar');

            const isSystemDefaultLanguage = Shopware.State.getters['context/isSystemDefaultLanguage'];
            if (!isSystemDefaultLanguage) {
                Shopware.State.commit('context/resetLanguageToDefault');
                this.$store.commit('cmsPageState/setIsSystemDefaultLanguage', isSystemDefaultLanguage);
            }

            this.page = this.pageRepository.create();
            this.page.sections = [];
        },

        async onSave() {
            this.isSaveSuccessful = false;

            if ((this.isSystemDefaultLanguage && !this.page.name) || !this.page.type) {
                this.createNotificationWarning({
                    message: this.$tc('sw-cms.detail.notification.messageMissingFields'),
                });

                return Promise.reject();
            }

            const { type, id } = this.$route.params;
            if (type === 'category') {
                const category = await this.categoryRepository.get(id);

                if (category) {
                    this.page.categories.add(category);
                }
            }

            this.deleteEntityAndRequiredConfigKey(this.page.sections);

            this.isLoading = true;

            return this.pageRepository.save(this.page).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                this.$router.push({ name: 'sw.cms.detail', params: { id: this.page.id } });
            }).catch((exception) => {
                this.isLoading = false;

                this.createNotificationError({
                    message: exception.message,
                });

                return Promise.reject(exception);
            });
        },

        onWizardComplete() {
            if (this.page.type === 'product_list' || this.page.type === 'product_detail') {
                this.onPageTypeChange();
            }

            this.wizardComplete = true;
            this.onSave();
        },
    },
});
