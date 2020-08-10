import template from './sw-settings-product-feature-sets-detail.html.twig';

const { Component, Mixin, StateDeprecated } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-product-feature-sets-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    props: {
        productFeatureSetId: {
            type: String,
            required: false,
            default: null
        }
    },

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            productFeatureSet: {},
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
            return this.placeholder(this.productFeatureSet, 'name');
        },

        // @deprecated tag:v6.4.0.0
        languageStore() {
            return StateDeprecated.getStore('language');
        },

        productFeatureSetsRepository() {
            return this.repositoryFactory.create('product_feature_set');
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

        ...mapPropertyErrors(
            'product_feature_set',
            ['name', 'description', 'features.id']
        )
    },

    watch: {
        productFeatureSetId() {
            if (!this.productFeatureSetId) {
                this.createdComponent();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (this.productFeatureSetId) {
                this.productFeatureSetId = this.$route.params.id;
                this.productFeatureSetsRepository
                    .get(this.productFeatureSetId, Shopware.Context.api)
                    .then((productFeatureSet) => {
                        this.productFeatureSet = productFeatureSet;
                        this.isLoading = false;
                    });
                return;
            }

            this.productFeatureSet = this.productFeatureSetsRepository.create(Shopware.Context.api);
            this.isLoading = false;
        },

        loadEntityData() {
            this.productFeatureSetsRepository.get(this.productFeatureSetId, Shopware.Context.api)
                .then((productFeatureSet) => {
                    this.productFeatureSet = productFeatureSet;
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.productFeatureSetsRepository.save(this.productFeatureSet, Shopware.Context.api).then(() => {
                this.isSaveSuccessful = true;
                if (!this.productFeatureSetId) {
                    this.$router.push({
                        name: 'sw.settings.product.feature.sets.detail',
                        params: { id: this.productFeatureSet.id }
                    });
                }
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-product-feature-sets.detail.notificationErrorMessage')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.product.feature.sets.index' });
        },

        abortOnLanguageChange() {
            return this.productFeatureSetsRepository.hasChanges(this.productFeatureSet);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        }
    }
});
