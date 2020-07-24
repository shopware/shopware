import template from './sw-product-detail.html.twig';
import swProductDetailState from './state';
import errorConfiguration from './error.cfg.json';
import './sw-product-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { hasOwnProperty } = Shopware.Utils.object;
const { mapPageErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-detail', {
    template,

    inject: ['mediaService', 'repositoryFactory', 'numberRangeService', 'seoUrlService', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('product.editor');
            },
            method: 'onSave'
        },
        ESCAPE: 'onCancel'
    },

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
            isSaveSuccessful: false,
            cloning: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'localMode'
        ]),

        ...mapGetters('swProductDetail', [
            'productRepository',
            'isLoading',
            'isChild',
            'defaultCurrency',
            'defaultFeatureSet'
        ]),

        ...mapPageErrors(errorConfiguration),

        identifier() {
            return this.productTitle;
        },

        productTitle() {
            // when product is variant
            if (this.isChild && this.product) {
                return this.getInheritTitle();
            }

            // return name
            return this.placeholder(this.product, 'name', this.$tc('sw-product.detail.textHeadline'));
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        mediaRepository() {
            if (this.product && this.product.media) {
                return this.repositoryFactory.create(
                    this.product.media.entity,
                    this.product.media.source
                );
            }
            return null;
        },

        featureSetRepository() {
            return this.repositoryFactory.create('product_feature_set');
        },

        productCriteria() {
            const criteria = new Criteria();

            criteria.getAssociation('media')
                .addSorting(Criteria.sort('position', 'ASC'));

            criteria.getAssociation('properties')
                .addSorting(Criteria.sort('name', 'ASC'));

            criteria.getAssociation('prices')
                .addSorting(Criteria.sort('quantityStart', 'ASC', true));

            criteria.getAssociation('tags')
                .addSorting(Criteria.sort('name', 'ASC'));

            criteria.getAssociation('seoUrls')
                .addFilter(Criteria.equals('isCanonical', true));

            criteria.getAssociation('crossSellings')
                .addSorting(Criteria.sort('position', 'ASC'))
                .getAssociation('assignedProducts')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('product')
                .getAssociation('product')
                .addAssociation('options.group');

            criteria
                .addAssociation('cover')
                .addAssociation('categories')
                .addAssociation('visibilities.salesChannel')
                .addAssociation('options')
                .addAssociation('configuratorSettings.option')
                .addAssociation('unit')
                .addAssociation('productReviews')
                .addAssociation('seoUrls')
                .addAssociation('mainCategories')
                .addAssociation('options.group')
                .addAssociation('customFieldSets')
                .addAssociation('featureSet');

            return criteria;
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'product'));
            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },

        defaultFeatureSetCriteria() {
            const criteria = new Criteria(1, 1);

            criteria
                .addSorting(Criteria.sort('createdAt', 'ASC'))
                .addFilter(Criteria.equalsAny('name', ['Default', 'Standard']));

            return criteria;
        },

        tooltipSave() {
            if (!this.acl.can('product.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('product.creator'),
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
        }
    },

    beforeCreate() {
        Shopware.State.registerModule('swProductDetail', swProductDetailState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('swProductDetail');
    },

    destroyed() {
        this.destroyedComponent();
    },

    watch: {
        productId() {
            this.destroyedComponent();
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            // when create
            if (!this.productId) {
                // set language to system language
                if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                    Shopware.State.commit('context/resetLanguageToDefault');
                }
            }

            // initialize default state
            this.initState();

            this.$root.$on('sidebar-toggle-open', this.openMediaSidebar);
            this.$root.$on('media-remove', (mediaId) => {
                this.removeMediaItem(mediaId);
            });
            this.$root.$on('product-reload', () => {
                this.loadAll();
            });
        },

        destroyedComponent() {
            this.$root.$off('sidebar-toggle-open');
            this.$root.$off('media-remove');
            this.$root.$off('product-reload');
        },

        initState() {
            Shopware.State.commit('swProductDetail/setApiContext', Shopware.Context.api);

            // when product exists
            if (this.productId) {
                return this.loadState();
            }

            // When no product id exists init state and new product with the repositoryFactory
            return this.createState().then(() => {
                // create new product number
                this.numberRangeService.reserve('product', '', true).then((response) => {
                    this.productNumberPreview = response.number;
                    this.product.productNumber = response.number;
                });
            });
        },

        loadState() {
            Shopware.State.commit('swProductDetail/setLocalMode', false);
            Shopware.State.commit('swProductDetail/setProductId', this.productId);

            return this.loadAll();
        },

        loadAll() {
            return Promise.all([
                this.loadProduct(),
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadAttributeSet()
            ]);
        },

        createState() {
            // set local mode
            Shopware.State.commit('swProductDetail/setLocalMode', true);

            Shopware.State.commit('swProductDetail/setLoading', ['product', true]);

            // create empty product
            Shopware.State.commit('swProductDetail/setProduct', this.productRepository.create(Shopware.Context.api));
            Shopware.State.commit('swProductDetail/setProductId', this.product.id);

            // fill empty data
            this.product.active = true;
            this.product.taxId = null;

            this.product.metaTitle = '';
            this.product.additionalText = '';

            return Promise.all([
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadAttributeSet(),
                this.loadDefaultFeatureSet()
            ]).then(() => {
                // set default product price
                this.product.price = [{
                    currencyId: this.defaultCurrency.id,
                    net: null,
                    linked: true,
                    gross: null
                }];

                this.product.featureSet = this.defaultFeatureSet;

                Shopware.State.commit('swProductDetail/setLoading', ['product', false]);
            });
        },

        loadProduct() {
            Shopware.State.commit('swProductDetail/setLoading', ['product', true]);

            this.productRepository.get(
                this.productId || this.product.id,
                Shopware.Context.api,
                this.productCriteria
            ).then((res) => {
                Shopware.State.commit('swProductDetail/setProduct', res);

                if (this.product.parentId) {
                    this.loadParentProduct();
                } else {
                    Shopware.State.commit('swProductDetail/setParentProduct', {});
                }

                Shopware.State.commit('swProductDetail/setLoading', ['product', false]);
            });
        },

        loadParentProduct() {
            Shopware.State.commit('swProductDetail/setLoading', ['parentProduct', true]);

            return this.productRepository.get(this.product.parentId, Shopware.Context.api, this.productCriteria)
                .then((res) => {
                    Shopware.State.commit('swProductDetail/setParentProduct', res);
                }).then(() => {
                    Shopware.State.commit('swProductDetail/setLoading', ['parentProduct', false]);
                });
        },

        loadCurrencies() {
            Shopware.State.commit('swProductDetail/setLoading', ['currencies', true]);

            return this.currencyRepository.search(new Criteria(1, 500), Shopware.Context.api).then((res) => {
                Shopware.State.commit('swProductDetail/setCurrencies', res);
            }).then(() => {
                Shopware.State.commit('swProductDetail/setLoading', ['currencies', false]);
            });
        },

        loadTaxes() {
            Shopware.State.commit('swProductDetail/setLoading', ['taxes', true]);

            return this.taxRepository.search(new Criteria(1, 500), Shopware.Context.api).then((res) => {
                Shopware.State.commit('swProductDetail/setTaxes', res);
            }).then(() => {
                Shopware.State.commit('swProductDetail/setLoading', ['taxes', false]);
            });
        },

        loadAttributeSet() {
            Shopware.State.commit('swProductDetail/setLoading', ['customFieldSets', true]);

            return this.customFieldSetRepository.search(this.customFieldSetCriteria, Shopware.Context.api).then((res) => {
                Shopware.State.commit('swProductDetail/setAttributeSet', res);
            }).then(() => {
                Shopware.State.commit('swProductDetail/setLoading', ['customFieldSets', false]);
            });
        },

        loadDefaultFeatureSet() {
            Shopware.State.commit('swProductDetail/setLoading', ['defaultFeatureSet', true]);

            return this.featureSetRepository.search(this.defaultFeatureSetCriteria, Shopware.Context.api).then((res) => {
                Shopware.State.commit('swProductDetail/setDefaultFeatureSet', res);
            }).then(() => {
                Shopware.State.commit('swProductDetail/setLoading', ['defaultFeatureSet', false]);
            });
        },

        abortOnLanguageChange() {
            return Shopware.State.getters['swProductDetail/hasChanges'];
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.initState();
        },

        openMediaSidebar() {
            // Check if we have a reference to the component before calling a method
            if (!hasOwnProperty(this.$refs, 'mediaSidebarItem')
                || !this.$refs.mediaSidebarItem) {
                return;
            }
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

            return this.saveProduct().then(this.onSaveFinished);
        },

        onSaveFinished(response) {
            const updatePromises = [];

            if (Shopware.State.list().includes('swSeoUrl')) {
                const seoUrls = Shopware.State.getters['swSeoUrl/getNewOrModifiedUrls']();
                const defaultSeoUrl = Shopware.State.get('swSeoUrl').defaultSeoUrl;

                if (seoUrls) {
                    seoUrls.forEach(seoUrl => {
                        if (!seoUrl.seoPathInfo) {
                            seoUrl.seoPathInfo = defaultSeoUrl.seoPathInfo;
                            seoUrl.isModified = false;
                        } else {
                            seoUrl.isModified = true;
                        }

                        updatePromises.push(this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId));
                    });
                }

                if (response === 'empty' && seoUrls.length > 0) {
                    response = 'success';
                }
            }

            Promise.all(updatePromises).then(() => {
                this.$root.$emit('seo-url-save-finish');
            }).then(() => {
                switch (response) {
                    case 'empty': {
                        this.isSaveSuccessful = true;
                        Shopware.State.commit('error/resetApiErrors');
                        break;
                    }

                    case 'success': {
                        this.isSaveSuccessful = true;

                        break;
                    }

                    default: {
                        const titleSaveError = this.$tc('global.default.error');
                        const messageSaveError = this.$tc(
                            'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'
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

        onCancel() {
            this.$router.push({ name: 'sw.product.index' });
        },

        saveProduct() {
            Shopware.State.commit('swProductDetail/setLoading', ['product', true]);

            return new Promise((resolve) => {
                // check if product exists
                if (!this.productRepository.hasChanges(this.product)) {
                    Shopware.State.commit('swProductDetail/setLoading', ['product', false]);
                    resolve('empty');
                    Shopware.State.commit('swProductDetail/setLoading', ['product', false]);
                    return;
                }

                // save product
                this.productRepository.save(this.product, Shopware.Context.api).then(() => {
                    this.loadAll().then(() => {
                        Shopware.State.commit('swProductDetail/setLoading', ['product', false]);

                        resolve('success');
                    });
                }).catch((response) => {
                    Shopware.State.commit('swProductDetail/setLoading', ['product', false]);
                    resolve(response);
                });
            });
        },

        onAddItemToProduct(mediaItem) {
            if (this._checkIfMediaIsAlreadyUsed(mediaItem.id)) {
                this.createNotificationInfo({
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated')
                });
                return false;
            }

            this.addMedia(mediaItem).then((mediaId) => {
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

        addMedia(mediaItem) {
            Shopware.State.commit('swProductDetail/setLoading', ['media', true]);

            // return error if media exists
            if (this.product.media.has(mediaItem.id)) {
                Shopware.State.commit('swProductDetail/setLoading', ['media', false]);
                // eslint-disable-next-line prefer-promise-reject-errors
                return Promise.reject('A media item with this id exists');
            }

            const newMedia = this.mediaRepository.create(Shopware.Context.api);
            newMedia.mediaId = mediaItem.id;

            return new Promise((resolve) => {
                // if no other media exists
                if (this.product.media.length === 0) {
                    // set media item as cover
                    newMedia.position = 0;
                    this.product.coverId = newMedia.id;
                }
                this.product.media.add(newMedia);

                Shopware.State.commit('swProductDetail/setLoading', ['media', false]);

                resolve(newMedia.mediaId);
                return true;
            });
        },

        removeMediaItem(state, mediaId) {
            const media = this.product.media.find((mediaItem) => mediaItem.mediaId === mediaId);

            // remove cover id if mediaId matches
            if (this.product.coverId === media.id) {
                this.product.coverId = null;
            }

            this.product.media.remove(mediaId);
        },

        onCoverChange(mediaId) {
            if (!mediaId || mediaId.length < 0) {
                return;
            }

            const media = this.product.media.find((mediaItem) => mediaItem.mediaId === mediaId);

            if (media) {
                this.product.coverId = media.id;
            }
        },

        _checkIfMediaIsAlreadyUsed(mediaId) {
            return this.product.media.some((productMedia) => {
                return productMedia.mediaId === mediaId;
            });
        },

        getInheritTitle() {
            if (
                this.product.hasOwnProperty('translated') &&
                this.product.translated.hasOwnProperty('name') &&
                this.product.translated.name !== null
            ) {
                return this.product.translated.name;
            }
            if (this.product.name !== null) {
                return this.product.name;
            }
            if (this.parentProduct && this.parentProduct.hasOwnProperty('translated')) {
                const pProduct = this.parentProduct;
                return pProduct.translated.hasOwnProperty('name') ? pProduct.translated.name : pProduct.name;
            }
            return '';
        },

        onDuplicate() {
            this.cloning = true;
        },

        onDuplicateFinish(duplicate) {
            this.cloning = false;
            this.$router.push({ name: 'sw.product.detail', params: { id: duplicate.id } });
        }
    }
});
