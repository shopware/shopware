/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-settings-country-create.html.twig';
import { getApiContext, getAppContext, Utils } from '../dependencies';

const utils = Utils;

/**
 * @public
 */
export default {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.country.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            getApiContext().languageId = getApiContext().systemLanguageId;

            if (this.$route.params.id) {
                this.country = this.countryRepository.create(getApiContext(), this.$route.params.id);
                this.country.customerTax = {
                    amount: 0,
                    currencyId: getAppContext().systemCurrencyId,
                    enabled: false,
                };
                this.country.companyTax = {
                    amount: 0,
                    currencyId: getAppContext().systemCurrencyId,
                    enabled: false,
                };
                this.countryId = this.country.id;
                this.countryStateRepository = this.repositoryFactory.create(
                    this.country.states.entity,
                    this.country.states.source,
                );
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.settings.country.detail',
                params: { id: this.country.id },
            });
        },
    },
};
