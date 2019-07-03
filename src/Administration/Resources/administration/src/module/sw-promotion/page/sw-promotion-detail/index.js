import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-promotion-detail.html.twig';
import errorConfig from './error-config.json';

const { mapPageErrors } = Component.getComponentHelper();

Component.register('sw-promotion-detail', {
    template,

    inject: ['repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('promotion')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
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
            promotion: null,
            isLoading: false,
            isSaveSuccessful: false
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
        tooltipSave() {
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
            if (!this.promotionId) {
                this.promotion = this.promotionRepository.create(this.context);

                // TODO check if numberrange is configured
                // if not show modal and link to settings!
            } else {
                this.loadEntityData();
            }
        },

        loadEntityData() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('salesChannels');

            this.promotionRepository.get(this.promotionId, this.context, criteria).then((promotion) => {
                this.promotion = promotion;
            });
        },

        abortOnLanguageChange() {
            return this.promotionRepository.hasChanges(this.promotion);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.$emit('save');
            this.isLoading = true;

            return this.promotionRepository.save(this.promotion, this.context).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    title: this.$tc('global.notification.notificationSaveErrorTitle'),
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessage',
                        0,
                        { entityName: this.promotion.name }
                    )
                });
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.promotion.index' });
        }
    }
});
