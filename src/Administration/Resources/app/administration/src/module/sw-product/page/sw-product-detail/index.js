import template from './sw-product-detail.html.twig';
import swProductDetailState from './state';
import errorConfiguration from './error.cfg.json';
import './sw-product-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria, ChangesetGenerator } = Shopware.Data;
const { hasOwnProperty, cloneDeep } = Shopware.Utils.object;
const { mapPageErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();
const type = Shopware.Utils.types;

Component.register('sw-product-detail', {
    template,

    inject: ['mediaService', 'repositoryFactory', 'numberRangeService', 'seoUrlService', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('product.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        productId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            productNumberPreview: '',
            isSaveSuccessful: false,
            cloning: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'localMode',
            'advancedModeSetting',
            'modeSettings',
        ]),

        ...mapGetters('swProductDetail', [
            'productRepository',
            'isLoading',
            'isChild',
            'defaultCurrency',
            'defaultFeatureSet',
            'showModeSetting',
            'advanceModeEnabled',
        ]),

        ...mapPageErrors(errorConfiguration),

        ...mapState('cmsPageState', [
            'currentPage',
        ]),

        identifier() {
            return this.productTitle;
        },

        productTitle() {
            // when product is variant
            if (this.isChild && this.product) {
                return this.getInheritTitle();
            }

            if (!this.$i18n) {
                return '';
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
                    this.product.media.source,
                );
            }
            return null;
        },

        featureSetRepository() {
            return this.repositoryFactory.create('product_feature_set');
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        userModeSettingsRepository() {
            return this.repositoryFactory.create('user_config');
        },

        userModeSettingsCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('key', 'mode.setting.advancedModeSettings'));
            criteria.addFilter(Criteria.equals('userId', this.currentUser && this.currentUser.id));

            return criteria;
        },

        productCriteria() {
            const criteria = new Criteria();

            criteria.getAssociation('media')
                .addSorting(Criteria.sort('position', 'ASC'));

            criteria.getAssociation('properties')
                .addSorting(Criteria.sort('name', 'ASC', true));

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
                .addAssociation('featureSet')
                .addAssociation('cmsPage')
                .addAssociation('featureSet');

            criteria.getAssociation('manufacturer')
                .addAssociation('media');

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

        taxCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('position'));

            return criteria;
        },

        tooltipSave() {
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

        getModeSettingGeneralTab() {
            return [
                {
                    key: 'general_information',
                    label: 'sw-product.detailBase.cardTitleProductInfo',
                    enabled: true,
                    name: 'general',
                },
                {
                    key: 'prices',
                    label: 'sw-product.detailBase.cardTitlePrices',
                    enabled: true,
                    name: 'general',
                },
                {
                    key: 'deliverability',
                    label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
                    enabled: true,
                    name: 'general',
                },
                {
                    key: 'visibility_structure',
                    label: 'sw-product.detailBase.cardTitleAssignment',
                    enabled: true,
                    name: 'general',
                },
                {
                    key: 'media',
                    label: 'sw-product.detailBase.cardTitleMedia',
                    enabled: true,
                    name: 'general',
                },
                {
                    key: 'labelling',
                    label: 'sw-product.detailBase.cardTitleSettings',
                    enabled: true,
                    name: 'general',
                },
            ];
        },

        getModeSettingSpecificationsTab() {
            return [
                {
                    key: 'measures_packaging',
                    label: 'sw-product.specifications.cardTitleMeasuresPackaging',
                    enabled: true,
                    name: 'specifications',
                },
                {
                    key: 'properties',
                    label: 'sw-product.specifications.cardTitleProperties',
                    enabled: true,
                    name: 'specifications',
                },
                {
                    key: 'essential_characteristics',
                    label: 'sw-product.specifications.cardTitleEssentialCharacteristics',
                    enabled: true,
                    name: 'specifications',
                },
                {
                    key: 'custom_fields',
                    label: 'sw-product.specifications.cardTitleCustomFields',
                    enabled: true,
                    name: 'specifications',
                },
            ];
        },

        showAdvanceModeSetting() {
            if (this.isChild) {
                return false;
            }

            const routes = [
                'sw.product.detail.base',
                'sw.product.detail.specifications',
            ];

            return routes.includes(this.$route.name);
        },
    },

    watch: {
        productId() {
            this.destroyedComponent();
            this.createdComponent();
        },
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

    methods: {
        createdComponent() {
            Shopware.State.dispatch('cmsPageState/resetCmsPageState');

            // when create
            if (!this.productId) {
                // set language to system language
                if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                    Shopware.State.commit('context/resetLanguageToDefault');
                }
            }

            // initialize default state
            this.initState();

            /**
             * @deprecated tag:v6.5.0 - The event listener "sidebar-toggle-open" will be removed because
             * the function that called it was deleted.
             */
            this.$root.$on('sidebar-toggle-open', this.openMediaSidebar);

            this.$root.$on('media-remove', (mediaId) => {
                this.removeMediaItem(mediaId);
            });
            this.$root.$on('product-reload', () => {
                this.loadAll();
            });

            this.initAdvancedModeSettings();
        },

        destroyedComponent() {
            /**
             * @deprecated tag:v6.5.0 - The event listener "sidebar-toggle-open" will be removed because
             * the function that called it was deleted.
             */
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

        initAdvancedModeSettings() {
            Shopware.State.commit('swProductDetail/setAdvancedModeSetting', this.getAdvancedModeDefaultSetting());

            this.getAdvancedModeSetting();
        },

        createUserModeSetting() {
            const newModeSettings = this.userModeSettingsRepository.create();
            newModeSettings.key = 'mode.setting.advancedModeSettings';
            newModeSettings.userId = this.currentUser && this.currentUser.id;
            return newModeSettings;
        },

        getAdvancedModeDefaultSetting() {
            const defaultSettings = this.createUserModeSetting();
            defaultSettings.value = {
                advancedMode: {
                    label: 'sw-product.general.textAdvancedMode',
                    enabled: true,
                },
                settings: [
                    ...this.getModeSettingGeneralTab,
                    ...this.getModeSettingSpecificationsTab,
                ],
            };
            return defaultSettings;
        },

        getAdvancedModeSetting() {
            return this.userModeSettingsRepository.search(this.userModeSettingsCriteria)
                .then((items) => {
                    if (!items.total) {
                        return;
                    }

                    Shopware.State.commit('swProductDetail/setAdvancedModeSetting', items.first());
                    Shopware.State.commit('swProductDetail/setModeSettings', this.changeModeSettings());
                });
        },

        saveAdvancedMode() {
            Shopware.State.commit('swProductDetail/setLoading', ['advancedMode', true]);
            this.userModeSettingsRepository.save(this.advancedModeSetting)
                .then(() => {
                    this.getAdvancedModeSetting().then(() => {
                        Shopware.State.commit('swProductDetail/setLoading', ['advancedMode', false]);
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                    });
                });
        },

        onChangeSetting() {
            Shopware.State.commit('swProductDetail/setAdvancedModeSetting', this.advancedModeSetting);
            this.saveAdvancedMode();
        },

        changeModeSettings() {
            const enabledModeItems = this.advancedModeSetting.value.settings.filter((item) => item.enabled);
            if (!enabledModeItems.length) {
                return [];
            }

            return enabledModeItems.map(item => item.key);
        },

        onChangeSettingItem() {
            Shopware.State.commit('swProductDetail/setModeSettings', this.changeModeSettings());
            this.saveAdvancedMode();
        },

        loadState() {
            Shopware.State.commit('swProductDetail/setLocalMode', false);
            Shopware.State.commit('swProductDetail/setProductId', this.productId);
            Shopware.State.commit('shopwareApps/setSelectedIds', [this.productId]);

            return this.loadAll();
        },

        loadAll() {
            return Promise.all([
                this.loadProduct(),
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadAttributeSet(),
            ]);
        },

        createState() {
            // set local mode
            Shopware.State.commit('swProductDetail/setLocalMode', true);
            Shopware.State.commit('shopwareApps/setSelectedIds', []);

            Shopware.State.commit('swProductDetail/setLoading', ['product', true]);

            // create empty product
            Shopware.State.commit('swProductDetail/setProduct', this.productRepository.create());
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
                this.loadDefaultFeatureSet(),
            ]).then(() => {
                // set default product price and empty purchase price
                this.product.price = [{
                    currencyId: this.defaultCurrency.id,
                    net: null,
                    linked: true,
                    gross: null,
                }];

                this.product.purchasePrices = [{
                    currencyId: this.defaultCurrency.id,
                    net: 0,
                    linked: true,
                    gross: 0,
                }];

                if (this.defaultFeatureSet && this.defaultFeatureSet.length > 0) {
                    this.product.featureSetId = this.defaultFeatureSet[0].id;
                }

                Shopware.State.commit('swProductDetail/setLoading', ['product', false]);
            });
        },

        loadProduct() {
            Shopware.State.commit('swProductDetail/setLoading', ['product', true]);

            this.productRepository.get(
                this.productId || this.product.id,
                Shopware.Context.api,
                this.productCriteria,
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

            return this.currencyRepository.search(new Criteria(1, 500)).then((res) => {
                Shopware.State.commit('swProductDetail/setCurrencies', res);
            }).then(() => {
                Shopware.State.commit('swProductDetail/setLoading', ['currencies', false]);
            });
        },

        loadTaxes() {
            Shopware.State.commit('swProductDetail/setLoading', ['taxes', true]);

            return this.taxRepository.search(this.taxCriteria).then((res) => {
                Shopware.State.commit('swProductDetail/setTaxes', res);
            }).then(() => {
                Shopware.State.commit('swProductDetail/setLoading', ['taxes', false]);
            });
        },

        loadAttributeSet() {
            Shopware.State.commit('swProductDetail/setLoading', ['customFieldSets', true]);

            return this.customFieldSetRepository.search(this.customFieldSetCriteria).then((res) => {
                Shopware.State.commit('swProductDetail/setAttributeSet', res);
            }).finally(() => {
                Shopware.State.commit('swProductDetail/setLoading', ['customFieldSets', false]);
            });
        },

        loadDefaultFeatureSet() {
            Shopware.State.commit('swProductDetail/setLoading', ['defaultFeatureSet', true]);

            return this.featureSetRepository.search(this.defaultFeatureSetCriteria).then((res) => {
                Shopware.State.commit('swProductDetail/setDefaultFeatureSet', res);
            }).then(() => {
                Shopware.State.commit('swProductDetail/setLoading', ['defaultFeatureSet', false]);
            });
        },

        abortOnLanguageChange() {
            return this.productRepository.hasChanges(this.product);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.initState();
        },

        /**
         * @deprecated tag:v6.5.0 - The method "openMediaSidebar" will be removed because
         * the function that called it was deleted.
         */
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
            if (!this.validateProductPurchase()) {
                this.createNotificationError({
                    message: this.$tc('sw-product.detail.errorMinMaxPurchase'),
                });

                return new Promise((res) => res());
            }

            this.validateProductListPrices();

            if (!this.productId) {
                if (this.productNumberPreview === this.product.productNumber) {
                    this.numberRangeService.reserve('product').then((response) => {
                        this.productNumberPreview = 'reserved';
                        this.product.productNumber = response.number;
                    });
                }
            }


            this.isSaveSuccessful = false;

            const pageOverrides = this.getCmsPageOverrides();

            if (type.isPlainObject(pageOverrides)) {
                this.product.slotConfig = cloneDeep(pageOverrides);
            }

            return this.saveProduct().then(this.onSaveFinished);
        },

        validateProductListPrices() {
            this.product.prices.forEach(advancedPrice => {
                this.validateListPrices(advancedPrice.price);
            });
            this.validateListPrices(this.product.price);
        },

        validateListPrices(prices) {
            if (!prices) {
                return;
            }

            prices.forEach(price => {
                if (!price.listPrice) {
                    return;
                }

                if (!price.listPrice.gross && !price.listPrice.net) {
                    price.listPrice = null;
                    return;
                }

                if (!price.listPrice.gross) {
                    price.listPrice.gross = 0;
                    return;
                }

                if (!price.listPrice.net) {
                    price.listPrice.net = 0;
                }
            });
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
                        const errorCode = response?.response?.data?.errors?.[0]?.code;

                        if (errorCode === 'CONTENT__DUPLICATE_PRODUCT_NUMBER') {
                            const titleSaveError = this.$tc('global.default.error');
                            const messageSaveError = this.$t(
                                'sw-product.notification.notificationSaveErrorProductNoAlreadyExists', {
                                    productNo: response.response.data.errors[0].meta.parameters.number,
                                },
                            );

                            this.createNotificationError({
                                title: titleSaveError,
                                message: messageSaveError,
                            });
                            break;
                        }

                        const errorDetail = response?.response?.data?.errors?.[0]?.detail;
                        const titleSaveError = this.$tc('global.default.error');
                        const messageSaveError = errorDetail ?? this.$tc(
                            'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                        );

                        this.createNotificationError({
                            title: titleSaveError,
                            message: messageSaveError,
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
                this.productRepository.save(this.product).then(() => {
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

        /**
         * @deprecated tag:v6.5.0 - The method "onAddItemToProduct" will be removed because
         * its relevant view was removed
         */
        onAddItemToProduct(mediaItem) {
            if (this._checkIfMediaIsAlreadyUsed(mediaItem.id)) {
                this.createNotificationInfo({
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated'),
                });
                return false;
            }

            this.addMedia(mediaItem).then((mediaId) => {
                this.$root.$emit('media-added', mediaId);
                return true;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-product.mediaForm.errorHeadline'),
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated'),
                });

                return false;
            });
            return true;
        },

        /**
         * @deprecated tag:v6.5.0 - The method "addMedia" will be removed because
         * the function that called it was deleted.
         */
        addMedia(mediaItem) {
            Shopware.State.commit('swProductDetail/setLoading', ['media', true]);

            // return error if media exists
            if (this.product.media.has(mediaItem.id)) {
                Shopware.State.commit('swProductDetail/setLoading', ['media', false]);
                // eslint-disable-next-line prefer-promise-reject-errors
                return Promise.reject('A media item with this id exists');
            }

            const newMedia = this.mediaRepository.create();
            newMedia.mediaId = mediaItem.id;
            newMedia.media = {
                url: mediaItem.url,
                id: mediaItem.id,
            };

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

        /**
         * @deprecated tag:v6.5.0 - The method "_checkIfMediaIsAlreadyUsed" will be removed because
         * the function that called it was deleted.
         */
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
        },

        validateProductPurchase() {
            if (this.product.maxPurchase && this.product.minPurchase > this.product.maxPurchase) {
                return false;
            }

            return true;
        },

        getCmsPageOverrides() {
            if (this.currentPage === null) {
                return null;
            }

            this.deleteSpecifcKeys(this.currentPage.sections);

            const changesetGenerator = new ChangesetGenerator();
            const { changes } = changesetGenerator.generate(this.currentPage);

            const slotOverrides = {};
            if (changes === null) {
                return slotOverrides;
            }

            if (type.isArray(changes.sections)) {
                changes.sections.forEach((section) => {
                    if (type.isArray(section.blocks)) {
                        section.blocks.forEach((block) => {
                            if (type.isArray(block.slots)) {
                                block.slots.forEach((slot) => {
                                    slotOverrides[slot.id] = slot.config;
                                });
                            }
                        });
                    }
                });
            }

            return slotOverrides;
        },

        deleteSpecifcKeys(sections) {
            if (!sections) {
                return;
            }

            sections.forEach((section) => {
                if (!section.blocks) {
                    return;
                }

                section.blocks.forEach((block) => {
                    if (!block.slots) {
                        return;
                    }

                    block.slots.forEach((slot) => {
                        if (!slot.config) {
                            return;
                        }

                        Object.values(slot.config).forEach((configField) => {
                            if (configField.entity) {
                                delete configField.entity;
                            }
                            if (configField.required) {
                                delete configField.required;
                            }
                            if (configField.type) {
                                delete configField.type;
                            }
                        });
                    });
                });
            });
        },
    },
});
