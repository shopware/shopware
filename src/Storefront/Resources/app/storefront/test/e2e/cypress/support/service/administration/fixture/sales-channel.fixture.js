const AdminFixtureService = require('./../fixture.service.js');

export default class AdminSalesChannelFixture extends AdminFixtureService {
    setSalesChannelFixture(salesChannelData, userData) {
        // Define the promises needed to get the related data needed for the sales channel
        const findCurrencyId = () => this.search('currency', {
            field: 'name',
            type: 'equals',
            value: 'Euro'
        });
        const findPaymentMethodId = () => this.search('payment-method', {
            field: 'name',
            type: 'equals',
            value: 'Invoice'
        });
        const findShippingMethodId = () => this.search('shipping-method', {
            field: 'name',
            type: 'equals',
            value: 'Standard'
        });
        const findCountryId = () => this.search('country', {
            field: 'iso',
            type: 'equals',
            value: 'DE'
        });
        const findLanguageId = () => this.search('language', {
            field: 'name',
            type: 'equals',
            value: 'Deutsch'
        });
        const findSalesChannelTypeId = () => this.search('sales-channel-type', {
            field: 'name',
            type: 'equals',
            value: 'Storefront'
        });
        const findCustomerGroupId = () => this.search('customer-group', {
            field: 'name',
            type: 'equals',
            value: 'Standard customer group'
        });
        const findNavigationCategoryId = () => this.search('category', {
            field: 'name',
            type: 'equals',
            value: 'Catalogue #1'
        });

        // Resolve promises
        return Promise.all([
            findCountryId(),
            findPaymentMethodId(),
            findNavigationCategoryId(),
            findCustomerGroupId(),
            findSalesChannelTypeId(),
            findLanguageId(),
            findShippingMethodId(),
            findCurrencyId()
        ]).then(([
             country,
             paymentMethod,
             navigationCategory,
             customerGroup,
             salesChannelType,
             language,
             shippingMethod,
             currency]) => {
            // Combine the responses of the promises to build the final request creating the sales channel
            return this.mergeFixtureWithData(salesChannelData, {
                currencyId: currency.id,
                paymentMethodId: paymentMethod.id,
                shippingMethodId: shippingMethod.id,
                countryId: country.id,
                languageId: language.id,
                typeId: salesChannelType.id,
                customerGroupId: customerGroup.id,
                navigationCategoryId: navigationCategory.id
            }, userData);
        }).then((finalChannelData) => {
            return this.apiClient.post('/v1/sales-channel?_response=true', finalChannelData);
        });
    }

    setSalesChannelDomain(salesChannelName, domainData = {
        url: `${Cypress.config('baseUrl')}/de`
    }) {
        const findCurrencyId = () => this.search('currency', {
            field: 'name',
            type: 'equals',
            value: 'Euro'
        });
        const findLanguageId = () => this.search('language', {
            field: 'name',
            type: 'equals',
            value: 'Deutsch'
        });
        const findSnippetSetId = () => this.search('snippet-set', {
            field: 'name',
            type: 'equals',
            value: 'BASE de-DE'
        });
        const findSalesChannelId = () => this.search('sales-channel', {
            field: 'name',
            type: 'equals',
            value: salesChannelName
        });

        // Resolve promises
        return Promise.all([
            findLanguageId(),
            findSnippetSetId(),
            findCurrencyId(),
            findSalesChannelId()
        ]).then(([language, snippetSet, currency, salesChannel]) => {
            // Combine the responses of the promises to build the final request creating the sales channel
            return this.mergeFixtureWithData(domainData, {
                currencyId: currency.id,
                languageId: language.id,
                snippetSetId: snippetSet.id,
                salesChannelId: salesChannel.id
            });
        }).then((result) => {
            return this.update({
                id: result.salesChannelId,
                type: 'sales-channel',
                data: {
                    domains: [{
                        currencyId: result.currencyId,
                        languageId: result.languageId,
                        snippetSetId: result.snippetSetId,
                        url: result.url
                    }]
                }
            });
        });
    }
}

global.AdminSalesChannelFixtureService = new AdminSalesChannelFixture();
