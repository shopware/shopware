import { mapPropertyErrors } from 'src/app/service/map-errors.service';
import template from './sw-settings-shipping-detail.html.twig';
import './sw-settings-shipping-detail.scss';
import swShippingDetailState from './state';

const { Mixin, Context } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'ruleConditionDataProviderService',
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        shippingMethodId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            logoMediaItem: null,
            uploadTag: 'sw-shipping-method-upload-tag',
            isSaveSuccessful: false,
            isProcessLoading: false,
            isLoading: false,
            currenciesLoading: false,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapState('swShippingDetail', [
            'shippingMethod',
            'currencies',
        ]),
        ...mapPropertyErrors('shippingMethod', [
            'name',
            'deliveryTimeId',
            'availabilityRuleId',
        ]),

        identifier() {
            return this.placeholder(this.shippingMethod, 'name');
        },

        shippingMethodRepository() {
            return this.repositoryFactory.create('shipping_method');
        },

        shippingMethodPricesRepository() {
            return this.repositoryFactory.create('shipping_method_price');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        isNewShippingMethod() {
            return Object.keys(this.shippingMethod).length > 0 && this.shippingMethod.isNew();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        deliveryTimeRepository() {
            return this.repositoryFactory.create('delivery_time');
        },

        deliveryTimeCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('unit'));
            criteria.addSorting(Criteria.sort('name'));
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

        ruleFilter() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    Criteria.contains('rule.moduleTypes.types', 'shipping'),
                    Criteria.equals('rule.moduleTypes', null),
                ],
            ));

            criteria.addAssociation('conditions');

            return criteria;
        },

        shippingMethodCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('prices');
            criteria.addAssociation('tags');
            criteria.getAssociation('prices').addAssociation('calculationRule');
            criteria.getAssociation('prices').addAssociation('rule');

            return criteria;
        },

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        'shippingMethod.mediaId'() {
            if (this.shippingMethod.mediaId) {
                this.setMediaItem({ targetId: this.shippingMethod.mediaId });
            }
        },

        shippingMethodId() {
            // We must reset the page if the user clicks his browsers back button and navigates back to create
            if (this.shippingMethodId === null) {
                this.createdComponent();
            }
        },
    },

    beforeCreate() {
        Shopware.State.registerModule('swShippingDetail', swShippingDetailState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('swShippingDetail');
    },

    methods: {
        createdComponent() {
            if (!this.shippingMethodId) {
                Shopware.State.commit('context/resetLanguageToDefault');

                const shippingMethod = this.shippingMethodRepository.create();
                const shippingMethodPrice = this.shippingMethodPricesRepository.create();
                shippingMethodPrice.calculation = 1;
                shippingMethodPrice.quantityStart = 1;
                shippingMethodPrice.shippingMethodId = shippingMethod.id;
                shippingMethodPrice.ruleId = null;
                shippingMethod.prices.add(shippingMethodPrice);
                Shopware.State.commit('swShippingDetail/setShippingMethod', shippingMethod);
            } else {
                this.loadEntityData();
                this.loadCustomFieldSets();
            }
            this.loadCurrencies();
        },

        onSaveRule(ruleId) {
            this.shippingMethod.availabilityRuleId = ruleId;
        },

        loadCurrencies() {
            this.currenciesLoading = true;
            this.currencyRepository.search(new Criteria(1, 500), Context.api).then((currencyResponse) => {
                Shopware.State.commit('swShippingDetail/setCurrencies', this.sortCurrencies(currencyResponse));
                this.currenciesLoading = false;
            });
        },

        loadEntityData() {
            this.isLoading = true;

            this.shippingMethodRepository.get(
                this.shippingMethodId,
                Shopware.Context.api,
                this.shippingMethodCriteria,
            ).then(res => {
                Shopware.State.commit('swShippingDetail/setShippingMethod', res);
                this.isLoading = false;
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('shipping_method').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        abortOnLanguageChange() {
            return this.shippingMethodRepository.hasChanges(this.shippingMethod);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            const titleSaveError = this.$tc('global.default.error');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
            );

            this.filterIncompletePrices();

            this.isSaveSuccessful = false;
            this.isProcessLoading = true;

            return this.shippingMethodRepository.save(this.shippingMethod, Context.api).then(() => {
                this.isProcessLoading = false;
                this.isSaveSuccessful = true;
                if (!this.shippingMethodId) {
                    this.$router.push({ name: 'sw.settings.shipping.detail', params: { id: this.shippingMethod.id } });
                }
                this.$refs.mediaSidebarItem.getList();
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError,
                });
                warn(this._name, exception.message, exception.response);
                this.isProcessLoading = false;
                throw exception;
            }).then(() => this.loadEntityData());
        },

        filterIncompletePrices() {
            this.getIncompletePrices().forEach(incompletePrice => {
                this.shippingMethod.prices.remove(incompletePrice.id);
            });
        },

        getIncompletePrices() {
            return this.shippingMethod.prices.filter(price => {
                return (!price.calculation && !price.calculationRuleId) || price._inNewMatrix;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.shipping.index' });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId, Context.api).then((updatedMedia) => {
                this.logoMediaItem = updatedMedia;
            });
            this.shippingMethod.mediaId = targetId;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        setMediaFromSidebar(mediaEntity) {
            this.logoMediaItem = mediaEntity;
            this.shippingMethod.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.logoMediaItem = null;
            this.shippingMethod.mediaId = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        sortCurrencies(currencies) {
            currencies.sort((a, b) => {
                if (a.isSystemDefault) {
                    return -1;
                }
                if (b.isSystemDefault) {
                    return 1;
                }
                if (a.translated.name < b.translated.name) {
                    return -1;
                }
                if (a.translated.name > b.translated.name) {
                    return 1;
                }
                return 0;
            });

            return currencies;
        },
    },
};
