/**
 * @package sales-channel
 */

const { Application, Defaults, State } = Shopware;

Application.addServiceProvider('domainLinkService', () => {
    return {
        getDomainLink,
    };
});

function getDomainLink(salesChannel) {
    if (salesChannel.type.id !== Defaults.storefrontSalesChannelTypeId) {
        return null;
    }

    if (salesChannel.domains.length === 0) {
        return null;
    }

    const adminLanguageDomain = salesChannel.domains.find((domain) => {
        return domain.languageId === State.get('session').languageId;
    });

    if (adminLanguageDomain) {
        return adminLanguageDomain.url;
    }

    const systemLanguageDomain = salesChannel.domains.find((domain) => {
        return domain.languageId === Defaults.systemLanguageId;
    });

    if (systemLanguageDomain) {
        return systemLanguageDomain.url;
    }

    return salesChannel.domains[0].url;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { getDomainLink as default };
