import './sw-settings-customer-group-detail.scss';
import template from './sw-settings-customer-group-detail.html.twig';

/**
 * @package customer-order
 */

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;
const types = Shopware.Utils.types;
const domainPlaceholderId = '124c71d524604ccbad6042edce3ac799';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'customFieldDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('customerGroup'),
    ],

    props: {
        customerGroupId: {
            type: String,
            required: false,
            default: null,
        },
    },

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },

        ESCAPE: 'onCancel',
    },

    data() {
        return {
            isLoading: false,
            customerGroup: null,
            isSaveSuccessful: false,
            openSeoModal: false,
            registrationTitleError: null,
            seoUrls: [],
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.customerGroup, 'name', '');
        },

        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        },

        seoUrlCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.customerGroup?.registrationSalesChannels.length) {
                const salesChannelIds = this.customerGroup.registrationSalesChannels?.getIds();

                criteria.addFilter(Criteria.equalsAny('salesChannelId', salesChannelIds));
            }

            criteria.addFilter(Criteria.equals('pathInfo', `/customer-group-registration/${this.customerGroupId}`));
            criteria.addFilter(Criteria.equals('languageId', Shopware.Context.api.languageId));
            criteria.addFilter(Criteria.equals('isCanonical', true));
            criteria.addAssociation('salesChannel.domains');
            criteria.addGroupField('seoPathInfo');
            criteria.addGroupField('salesChannelId');

            return criteria;
        },

        entityDescription() {
            return this.placeholder(
                this.customerGroup,
                'name',
                this.$tc('sw-settings-customer-group.detail.placeholderNewCustomerGroup'),
            );
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
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

        hasRegistration: {
            get() {
                return this.customerGroup && this.customerGroup.registration !== undefined;
            },
            set(value) {
                if (value) {
                    this.customerGroup.registration = this.customerGroupRegistrationRepository.create();
                } else {
                    this.customerGroup.registration = null;
                }
            },
        },

        technicalUrl() {
            return `${domainPlaceholderId}/customer-group-registration/${this.customerGroupId}#`;
        },

        ...mapPropertyErrors('customerGroup', ['name']),

        allowSave() {
            return this.customerGroup && this.customerGroup.isNew()
                ? this.acl.can('customer_groups.creator')
                : this.acl.can('customer_groups.editor');
        },

        showCustomFields() {
            return this.customerGroup && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        customerGroupId() {
            if (!this.customerGroupId) {
                this.createdComponent();
            }
        },
        'customerGroup.registrationTitle'() {
            this.registrationTitleError = null;
        },
        'customerGroup.registrationSalesChannels'() {
            this.loadSeoUrls();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.customerGroupId) {
                this.loadSeoUrls();
                this.loadCustomFieldSets();
                const criteria = new Criteria(1, 25);
                criteria.addAssociation('registrationSalesChannels');

                this.customerGroupRepository.get(this.customerGroupId, Shopware.Context.api, criteria)
                    .then((customerGroup) => {
                        this.customerGroup = customerGroup;
                        this.isLoading = false;
                    });
                return;
            }

            Shopware.State.commit('context/resetLanguageToDefault');
            this.customerGroup = this.customerGroupRepository.create();
            this.isLoading = false;
        },

        async loadSeoUrls() {
            if (!this.customerGroup?.registrationSalesChannels.length) {
                this.seoUrls = [];
                return;
            }
            this.seoUrls = await this.seoUrlRepository.search(this.seoUrlCriteria);
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('customer_group').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.customer.group.index' });
        },

        getSeoUrl(seoUrl) {
            let shopUrl = '';

            seoUrl.salesChannel.domains.forEach(domain => {
                if (domain.languageId === seoUrl.languageId) {
                    shopUrl = domain.url;
                }
            });

            return `${shopUrl}/${seoUrl.seoPathInfo}`;
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (
                Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId &&
                this.customerGroup.registrationActive &&
                types.isEmpty(this.customerGroup.registrationTitle)) {
                this.createNotificationError({
                    message: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                });

                this.registrationTitleError = new ShopwareError({
                    code: 'CUSTOMER_GROUP_REGISTERATION_MISSING_TITLE',
                    detail: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                });

                this.isLoading = false;
                this.isSaveSuccessful = false;
                return;
            }

            try {
                await this.customerGroupRepository.save(this.customerGroup);

                this.isSaveSuccessful = true;
                if (!this.customerGroupId) {
                    this.customerGroupId = this.customerGroup.id;
                    this.$router.push({ name: 'sw.settings.customer.group.detail', params: { id: this.customerGroup.id } });
                }

                this.customerGroup = await this.createdComponent();
            } catch (err) {
                this.isLoading = false;

                this.createNotificationError({
                    message: this.$tc('sw-settings-customer-group.detail.notificationErrorMessage'),
                });
            }
        },
    },
};
