import { handle, publish } from '@shopware-ag/admin-extension-sdk/es/channel';

export default function initializeContext(): void {
    // Handle incoming context requests from the ExtensionAPI
    handle('contextCurrency', () => {
        return {
            systemCurrencyId: Shopware.Context.app.systemCurrencyId ?? '',
            systemCurrencyISOCode: Shopware.Context.app.systemCurrencyISOCode ?? '',
        };
    });

    handle('contextEnvironment', () => {
        return Shopware.Context.app.environment ?? 'production';
    });

    handle('contextLanguage', () => {
        return {
            languageId: Shopware.Context.api.languageId ?? '',
            systemLanguageId: Shopware.Context.api.systemLanguageId ?? '',
        };
    });

    handle('contextLocale', () => {
        return {
            fallbackLocale: Shopware.Context.app.fallbackLocale ?? '',
            locale: Shopware.State.get('session').currentLocale ?? '',
        };
    });

    Shopware.State.watch((state) => {
        return {
            languageId: state.context.api.languageId,
            systemLanguageId: state.context.api.systemLanguageId,
        };
    }, ({ languageId, systemLanguageId }, { languageId: oldLanguageId, systemLanguageId: oldSystemLanguageId }) => {
        if (languageId === oldLanguageId && systemLanguageId === oldSystemLanguageId) {
            return;
        }

        void publish('contextLanguage', {
            languageId: languageId ?? '',
            systemLanguageId: systemLanguageId ?? '',
        });
    });

    Shopware.State.watch((state) => {
        return {
            fallbackLocale: state.context.app.fallbackLocale,
            locale: state.session.currentLocale,
        };
    }, ({ fallbackLocale, locale }, { fallbackLocale: oldFallbackLocale, locale: oldLocale }) => {
        if (fallbackLocale === oldFallbackLocale && locale === oldLocale) {
            return;
        }

        void publish('contextLocale', {
            locale: locale ?? '',
            fallbackLocale: fallbackLocale ?? '',
        });
    });
}
