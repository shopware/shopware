import template from './sw-settings-address.html.twig';
import './sw-settings-address.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            defaultCountry: null,
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        countryCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.multi('or', [
                Criteria.not('and', [Criteria.equals('salesChannels.id', null)]),
                Criteria.equals('iso', 'DE'),
            ]));

            return criteria;
        },

        addressFormatSettingsLink() {
            const route = {
                name: 'sw.settings.country.detail.address-handling',
                params: { id: this.defaultCountry },
            };
            const routeData = this.$router.resolve(route);

            return routeData.href;
        },
    },

    created() {
        this.createComponent();
    },

    methods: {
        async createComponent() {
            this.defaultCountry = await this.getDefaultCountry();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((err) => {
                this.isLoading = false;
                this.createNotificationError({
                    message: err,
                });
            });
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },

        async getDefaultCountry() {
            const ids = await this.countryRepository.searchIds(this.countryCriteria);

            return ids.data[0];
        },
    },
};
