import template from './sw-promotion-v2-detail.html.twig';
import errorConfig from './error-config.json';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-promotion-v2-detail', {
    template,

    inject: [
        'repositoryFactory',
        'acl'
    ],

    mixins: [
        'notification',
        'placeholder',
        Mixin.getByName('discard-detail-page-changes')('promotion')
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('promotion.editor');
            },
            method: 'onSave'
        },
        ESCAPE: 'onCancel'
    },

    props: {
        promotionId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            isLoading: false,
            promotion: null,
            isSaveSuccessful: false,
            saveCallbacks: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.promotion, 'name');
        },

        promotionRepository() {
            return this.repositoryFactory.create('promotion');
        },

        isCreateMode() {
            return this.$route.name === 'sw.promotion.v2.create.base';
        },

        promotionCriteria() {
            return (new Criteria(1, 1));
        },

        tooltipSave() {
            if (!this.acl.can('promotion.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('category.editor'),
                    showOnDisabledElements: true
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        },

        ...mapPageErrors(errorConfig)
    },

    created() {
        this.createdComponent();
    },

    watch: {
        promotionId() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (!this.promotionId) {
                this.promotion = this.promotionRepository.create(Shopware.Context.api);
                this.isLoading = false;

                return;
            }

            this.loadEntityData();
        },

        loadEntityData() {
            return this.promotionRepository.get(this.promotionId, Shopware.Context.api, this.promotionCriteria)
                .then((promotion) => {
                    this.promotion = promotion;
                    this.isLoading = false;
                });
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            this.isLoading = true;

            if (!this.promotionId) {
                return this.createPromotion();
            }

            return this.savePromotion();
        },

        createPromotion() {
            return this.savePromotion().then(() => {
                this.$router.push({ name: 'sw.promotion.v2.detail', params: { id: this.promotion.id } });
            });
        },

        savePromotion() {
            return this.promotionRepository.save(this.promotion, Shopware.Context.api).then(() => {
                this.loadEntityData();
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('global.notification.notificationSaveErrorMessage', 0, {
                        entityName: this.promotion.name
                    })
                });
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.promotion.v2.index' });
        }
    }
});
