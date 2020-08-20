import './sw-settings-customer-group-detail.scss';
import template from './sw-settings-customer-group-detail.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-customer-group-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('customerGroup')
    ],

    props: {
        customerGroupId: {
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
            isLoading: false,
            customerGroup: null,
            isSaveSuccessful: false,
            openSeoModal: false,
            seoUrls: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
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

        entityDescription() {
            return this.placeholder(
                this.customerGroup,
                'name',
                this.$tc('sw-settings-customer-group.detail.placeholderNewCustomerGroup')
            );
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

        hasRegistration: {
            get() {
                return this.customerGroup && this.customerGroup.registration !== undefined;
            },
            set(value) {
                if (value) {
                    this.customerGroup.registration = this.customerGroupRegistrationRepository.create(Shopware.Context.api);
                } else {
                    this.customerGroup.registration = null;
                }
            }
        },

        ...mapPropertyErrors('customerGroup', ['name'])
    },

    watch: {
        customerGroupId() {
            if (!this.customerGroupId) {
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
            if (this.customerGroupId) {
                this.loadSeoUrls();
                this.customerGroupRepository.get(this.customerGroupId, Shopware.Context.api).then((customerGroup) => {
                    this.customerGroup = customerGroup;
                    this.isLoading = false;
                });
                return;
            }

            Shopware.State.commit('context/resetLanguageToDefault');
            this.customerGroup = this.customerGroupRepository.create(Shopware.Context.api);
            this.isLoading = false;
        },

        async loadSeoUrls() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('pathInfo', `/customer-group-registration/${this.customerGroupId}`));
            criteria.addFilter(Criteria.equals('isCanonical', true));
            criteria.addAssociation('salesChannel.domains');
            criteria.addGroupField('seoPathInfo');
            criteria.addGroupField('salesChannelId');

            this.seoUrls = await this.seoUrlRepository.search(criteria, Shopware.Context.api);
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.customer.group.index' });
        },

        getSeoUrl(seoUrl) {
            let shopUrl = 'https://headless.shop';

            seoUrl.salesChannel.domains.forEach(domain => {
                shopUrl = domain.url;
            });

            return `${shopUrl}/${seoUrl.seoPathInfo}`;
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            try {
                await this.customerGroupRepository.save(this.customerGroup, Shopware.Context.api);

                this.isSaveSuccessful = true;
                if (!this.customerGroupId) {
                    this.$router.push({ name: 'sw.settings.customer.group.detail', params: { id: this.customerGroup.id } });
                }

                this.customerGroup = await this.customerGroupRepository.get(this.customerGroup.id, Shopware.Context.api);
            } catch (err) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-customer-group.detail.notificationErrorMessage')
                });
            }

            this.isLoading = false;
        }
    }
});
