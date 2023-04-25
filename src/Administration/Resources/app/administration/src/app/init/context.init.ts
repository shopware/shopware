/**
 * @package admin
 */

/* Is covered by E2E tests */
import { publish } from '@shopware-ag/admin-extension-sdk/es/channel';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeContext(): void {
    // Handle incoming context requests from the ExtensionAPI
    Shopware.ExtensionAPI.handle('contextCurrency', () => {
        return {
            systemCurrencyId: Shopware.Context.app.systemCurrencyId ?? '',
            systemCurrencyISOCode: Shopware.Context.app.systemCurrencyISOCode ?? '',
        };
    });

    Shopware.ExtensionAPI.handle('contextEnvironment', () => {
        return Shopware.Context.app.environment ?? 'production';
    });

    Shopware.ExtensionAPI.handle('contextLanguage', () => {
        return {
            languageId: Shopware.Context.api.languageId ?? '',
            systemLanguageId: Shopware.Context.api.systemLanguageId ?? '',
        };
    });

    Shopware.ExtensionAPI.handle('contextLocale', () => {
        return {
            fallbackLocale: Shopware.Context.app.fallbackLocale ?? '',
            locale: Shopware.State.get('session').currentLocale ?? '',
        };
    });

    Shopware.ExtensionAPI.handle('contextShopwareVersion', () => {
        return Shopware.Context.app.config.version ?? '';
    });

    Shopware.ExtensionAPI.handle('contextModuleInformation', (_, additionalInformation) => {
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extension) {
            return {
                modules: [],
            };
        }

        // eslint-disable-next-line max-len,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        const modules = Shopware.State.getters['extensionSdkModules/getRegisteredModuleInformation'](extension.baseUrl) as Array< {
            displaySearchBar: boolean,
            heading: string,
            id: string,
            locationId: string
        }>;

        return {
            modules,
        };
    });

    Shopware.ExtensionAPI.handle('contextAppInformation', (_, { _event_ }) => {
        const appOrigin = _event_.origin;
        const extension = Object.entries(Shopware.State.get('extensions')).find((ext) => {
            return ext[1].baseUrl.startsWith(appOrigin);
        });

        if (!extension || !extension[0] || !extension[1]) {
            const type: 'app'|'plugin' = 'app';

            return {
                name: 'unknown',
                type: type,
                version: '0.0.0',
            };
        }

        return {
            name: extension[0],
            type: extension[1].type,
            version: extension[1].version ?? '',
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
