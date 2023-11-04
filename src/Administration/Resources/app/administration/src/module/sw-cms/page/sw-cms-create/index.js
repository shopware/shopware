import template from './sw-cms-create.html.twig';

const { Mixin } = Shopware;
const utils = Shopware.Utils;

/**
 * @private
 * @package content
 */
export default {
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

            this.page = await this.assignToEntity(this.page);

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

        async assignToEntity(page) {
            const { type, id } = this.$route.params;

            if (!id || !type) {
                return page;
            }

            try {
                if (type === 'category') {
                    const category = await this.categoryRepository.get(id);

                    if (category) {
                        page.categories.push(category);
                    }
                }

                if (type.startsWith('ce_') || type.startsWith('custom_entity_')) {
                    const customEntityRepository = this.repositoryFactory.create(type);
                    const entity = await customEntityRepository.get(id);

                    if (entity) {
                        page.extensions[`${utils.string.camelCase(type)}SwCmsPage`].push(entity);
                    }
                }
            } catch (e) {
                this.createNotificationError({
                    message: this.$tc('sw-cms.create.notification.assignToEntityError'),
                });
            }

            return page;
        },

        onWizardComplete() {
            if (this.page.type === 'product_list' || this.page.type === 'product_detail') {
                this.onPageTypeChange(this.page.type);
            }

            this.wizardComplete = true;
            this.onSave();
        },
    },
};
