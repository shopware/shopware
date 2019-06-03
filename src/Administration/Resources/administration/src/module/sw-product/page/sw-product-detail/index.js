import { Component, Mixin, State } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
import template from './sw-product-detail.html.twig';
import swProductDetailState from './state';

Component.register('sw-product-detail', {
    template,

    inject: ['mediaService', 'repositoryFactory', 'context', 'numberRangeService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    props: {
        productId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            productNumberPreview: '',
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
            return this.placeholder(this.product, 'name');
        },

        languageStore() {
            return State.getStore('language');
        },

        ...mapState('swProductDetail', [
            'product',
            'localMode'
        ]),

        ...mapGetters('swProductDetail', [
            'productRepository',
            'isLoading'
        ])
    },

    beforeCreate() {
        const store = this.$store;

        // register a new module only if doesn't exists
        if (!(store && store.state && store.state.swProductDetail)) {
            this.$store.registerModule('swProductDetail', swProductDetailState);
        }
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.$store.unregisterModule('swProductDetail');
    },

    destroyed() {
        this.destroyedComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            // when create
            if (!this.productId) {
                // set language to system language
                if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                    this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
                }
            }

            // initialize default state
            this.initState();

            this.$root.$on('sw-product-media-form-open-sidebar', this.openMediaSidebar);
            this.$root.$on('media-remove', (mediaId) => {
                this.$store.commit('swProductDetail/removeMediaItem', mediaId);
            });
            this.$root.$on('product-reload', () => {
                this.$store.dispatch('swProductDetail/loadAll');
            });
        },

        destroyedComponent() {
            this.$root.$off('sw-product-media-form-open-sidebar');
            this.$root.$off('media-remove');
            this.$root.$off('product-reload');
        },

        initState() {
            // when product exists
            if (this.productId) {
                // Init state with the repositoryFactory
                return this.$store.dispatch('swProductDetail/loadState', {
                    productId: this.productId,
                    repositoryFactory: this.repositoryFactory,
                    context: this.context
                });
            }

            // When no product id exists init state and new product with the repositoryFactory
            return this.$store.dispatch('swProductDetail/createState', {
                repositoryFactory: this.repositoryFactory,
                context: this.context
            }).then(() => {
                // create new product number
                this.numberRangeService.reserve('product', '', true).then((response) => {
                    this.productNumberPreview = response.number;
                    this.product.productNumber = response.number;
                });
            });
        },

        abortOnLanguageChange() {
            return this.$store.getters['swProductDetail/hasChanges'];
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            this.context.languageId = languageId;
            this.initState();
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;

            if (!this.productId) {
                this.$router.push({ name: 'sw.product.detail', params: { id: this.product.id } });
            }
        },

        onSave() {
            if (!this.productId) {
                if (this.productNumberPreview === this.product.productNumber) {
                    this.numberRangeService.reserve('product').then((response) => {
                        this.productNumberPreview = 'reserved';
                        this.product.productNumber = response.number;
                    });
                }
            }

            this.isSaveSuccessful = false;

            return this.$store.dispatch('swProductDetail/saveProduct').then((res) => {
                switch (res) {
                    case 'empty': {
                        const titleSaveWarning = this.$tc('sw-product.detail.titleSaveWarning');
                        const messageSaveWarning = this.$tc('sw-product.detail.messageSaveWarning');

                        this.createNotificationWarning({
                            title: titleSaveWarning,
                            message: messageSaveWarning
                        });
                        break;
                    }

                    case 'success': {
                        this.isSaveSuccessful = true;

                        break;
                    }

                    default: {
                        const productName = this.product.translated ? this.product.translated.name : this.product.name;
                        const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
                        const messageSaveError = this.$tc(
                            'global.notification.notificationSaveErrorMessage', 0, { entityName: productName }
                        );

                        this.createNotificationError({
                            title: titleSaveError,
                            message: messageSaveError
                        });
                        break;
                    }
                }
            });
        },

        onAddItemToProduct(mediaItem) {
            if (this._checkIfMediaIsAlreadyUsed(mediaItem.id)) {
                this.createNotificationInfo({
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated')
                });
                return false;
            }

            this.$store.dispatch('swProductDetail/addMedia', mediaItem).then((mediaId) => {
                this.$root.$emit('media-added', mediaId);
                return true;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-product.mediaForm.errorHeadline'),
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated')
                });

                return false;
            });
            return true;
        },

        _checkIfMediaIsAlreadyUsed(mediaId) {
            return Object.values(this.product.media).some((productMedia) => {
                return productMedia.mediaId === mediaId;
            });
        }
    }
});
