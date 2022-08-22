import template from './sw-settings-product-feature-sets-detail.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-settings-product-feature-sets-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        productFeatureSetId: {
            type: String,
            required: false,
            default: null,
        },
    },

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            productFeatureSet: {},
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.productFeatureSet, 'name');
        },

        productFeatureSetsRepository() {
            return this.repositoryFactory.create('product_feature_set');
        },

        tooltipSave() {
            if (!this.acl.can('product_feature_sets.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('product_feature_sets.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        ...mapPropertyErrors(
            'productFeatureSet',
            ['name', 'description', 'features.id'],
        ),
    },

    watch: {
        productFeatureSetId() {
            if (!this.productFeatureSetId) {
                this.createdComponent();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (this.productFeatureSetId) {
                this.productFeatureSetId = this.$route.params.id;
                this.productFeatureSetsRepository.get(this.productFeatureSetId)
                    .then((productFeatureSet) => {
                        if (productFeatureSet.features && !productFeatureSet.features.length) {
                            productFeatureSet.features = [];
                        }

                        this.productFeatureSet = productFeatureSet;
                        this.isLoading = false;
                    });
                return;
            }

            this.productFeatureSet = this.productFeatureSetsRepository.create();
            this.isLoading = false;
        },

        loadEntityData() {
            this.productFeatureSetsRepository.get(this.productFeatureSetId)
                .then((productFeatureSet) => {
                    if (productFeatureSet.features && !productFeatureSet.features.length) {
                        productFeatureSet.features = [];
                    }

                    this.productFeatureSet = productFeatureSet;
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.productFeatureSetsRepository.save(this.productFeatureSet)
                .then(() => {
                    this.isSaveSuccessful = true;
                    if (!this.productFeatureSetId) {
                        this.$router.push({
                            name: 'sw.settings.product.feature.sets.detail',
                            params: { id: this.productFeatureSet.id },
                        });
                    }
                })
                .then(() => {
                    this.loadEntityData();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-product-feature-sets.detail.notificationErrorMessage'),
                    });
                })
                .finally(() => {
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
        },
    },
});
