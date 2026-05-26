/**
 * @sw-package framework
 */
import { watch } from 'vue';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initLanguageService() {
    Shopware.Application.addServiceProviderMiddleware('repositoryFactory', (repositoryFactory) => {
        // load the language when repositoryFactory is created
        initLanguageAutoFetchingService();

        return repositoryFactory;
    });

    watch(() => Shopware.Store.get('session').userPrivileges, initLanguageAutoFetchingService);
    watch(() => Shopware.Store.get('session').currentUser?.admin, initLanguageAutoFetchingService);
}

function initLanguageAutoFetchingService() {
    if (!canReadLanguage()) {
        return;
    }

    // eslint-disable-next-line @typescript-eslint/no-unused-expressions
    Shopware.Application.getContainer('service').languageAutoFetchingService;
}

function canReadLanguage() {
    const session = Shopware.Store.get('session');

    return session.currentUser?.admin === true || session.userPrivileges?.includes('language:read') === true;
}
