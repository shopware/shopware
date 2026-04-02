/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */

import './mixin/export-channel-filter.mixin';
import './extension/sw-order-list';
import './extension/sw-customer-list';

// No routes or navigation — this module only adds columns and filters to existing lists.
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-export-channel-tracking', {
    type: 'core',
    name: 'export-channel-tracking',
    title: 'sw-export-channel-tracking.general.mainMenuItemGeneral',
    description: 'sw-export-channel-tracking.general.descriptionTextModule',
    routeMiddleware: (next) => next(),

    snippets: {
        'de-DE': () => import('./snippet/de.json'),
        'en-GB': () => import('./snippet/en.json'),
    },
});
