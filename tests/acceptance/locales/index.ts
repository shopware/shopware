// Import all locale files
import enAdministrationOrder from './en/administration/order.json' with { type: 'json' };
import enAdministrationCommon from './en/administration/common.json' with { type: 'json' };
import enAdministrationLanding from './en/administration/landing.json' with { type: 'json' };
import enStorefrontAccount from './en/storefront/account.json' with { type: 'json' };
import enStorefrontCheckout from './en/storefront/checkout.json' with { type: 'json' };
import enStorefrontProduct from './en/storefront/product.json' with { type: 'json' };

import deAdministrationOrder from './de/administration/order.json' with { type: 'json' };
import deAdministrationCommon from './de/administration/common.json' with { type: 'json' };
import deAdministrationLanding from './de/administration/landing.json' with { type: 'json' };
import deStorefrontAccount from './de/storefront/account.json' with { type: 'json' };
import deStorefrontCheckout from './de/storefront/checkout.json' with { type: 'json' };
import deStorefrontProduct from './de/storefront/product.json' with { type: 'json' };

// Export the bundled resources for i18next
export const LOCALE_RESOURCES = {
    en: {
        'administration/order': enAdministrationOrder,
        'administration/common': enAdministrationCommon,
        'administration/landing': enAdministrationLanding,
        'storefront/account': enStorefrontAccount,
        'storefront/checkout': enStorefrontCheckout,
        'storefront/product': enStorefrontProduct,
    },
    de: {
        'administration/order': deAdministrationOrder,
        'administration/common': deAdministrationCommon,
        'administration/landing': deAdministrationLanding,
        'storefront/account': deStorefrontAccount,
        'storefront/checkout': deStorefrontCheckout,
        'storefront/product': deStorefrontProduct,
    },
} as const;

export const enNamespaces = {
    administration: {
        order: enAdministrationOrder,
        common: enAdministrationCommon,
        landingPage: enAdministrationLanding,
    },
    storefront: {
        account: enStorefrontAccount,
        checkout: enStorefrontCheckout,
        product: enStorefrontProduct,
    },
} as const;
