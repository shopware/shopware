import './acl';
import defaultSearchConfiguration from './default-search-configuration';

/**
 * @package customer-order
 */

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-newsletter-recipient-list', () => import('./page/sw-newsletter-recipient-list/index'));
Shopware.Component.register('sw-newsletter-recipient-detail', () => import('./page/sw-newsletter-recipient-detail/index'));
Shopware.Component.register('sw-newsletter-recipient-filter-switch', () => import('./component/sw-newsletter-recipient-filter-switch'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-newsletter-recipient', {
    type: 'core',
    name: 'newsletter-recipient',
    title: 'sw-newsletter-recipient.general.mainMenuItemGeneral',
    description: 'sw-newsletter-recipient.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
    icon: 'regular-megaphone',
    favicon: 'regular-megaphone',
    entity: 'newsletter_recipient',
    entityDisplayProperty: 'email',

    routes: {
        index: {
            component: 'sw-newsletter-recipient-list',
            path: 'index',
            meta: {
                privilege: 'newsletter_recipient.viewer',
            },

        },

        detail: {
            component: 'sw-newsletter-recipient-detail',
            path: 'detail/:id',
            meta: {
                privilege: 'newsletter_recipient.viewer',
                parentPath: 'sw.newsletter.recipient.index',
            },
        },
    },

    navigation: [{
        id: 'sw-newsletter-recipient',
        icon: 'regular-megaphone',
        color: '#FFD700',
        path: 'sw.newsletter.recipient.index',
        privilege: 'newsletter_recipient.viewer',
        label: 'sw-newsletter-recipient.general.mainMenuItemGeneral',
        parent: 'sw-marketing',
    }],

    defaultSearchConfiguration,
});
