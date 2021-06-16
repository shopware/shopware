import './page/sw-newsletter-recipient-list/index';
import './page/sw-newsletter-recipient-detail/index';
import './component/sw-newsletter-recipient-filter-switch';
import './acl';

const { Module } = Shopware;

Module.register('sw-newsletter-recipient', {
    type: 'core',
    name: 'newsletter-recipient',
    title: 'sw-newsletter-recipient.general.mainMenuItemGeneral',
    description: 'sw-newsletter-recipient.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
    icon: 'default-object-marketing',
    favicon: 'default-object-marketing',
    entity: 'newsletter_recipient',

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
        icon: 'default-object-marketing',
        color: '#FFD700',
        path: 'sw.newsletter.recipient.index',
        privilege: 'newsletter_recipient.viewer',
        label: 'sw-newsletter-recipient.general.mainMenuItemGeneral',
        parent: 'sw-marketing',
    }],
});
