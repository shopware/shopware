/**
 * @package customer-order
 */

Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'marketing',
        key: 'newsletter_recipient',
        roles: {
            viewer: {
                privileges: [
                    'newsletter_recipient:read',
                    'customer_group:read',
                    'sales_channel:read',
                    'salutation:read',
                    'customer:read',
                    'tag:read',
                    'user_config:read',
                    'user_config:create',
                    'user_config:update',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'newsletter_recipient:update',
                    Shopware.Service('privileges').getPrivileges('media.creator'),
                ],
                dependencies: [
                    'newsletter_recipient.viewer',
                ],
            },
            creator: {
                privileges: [
                    'newsletter_recipient:create',
                ],
                dependencies: [
                    'newsletter_recipient.viewer',
                    'newsletter_recipient.editor',
                ],
            },
            deleter: {
                privileges: [
                    'newsletter_recipient:delete',
                ],
                dependencies: [
                    'newsletter_recipient.viewer',
                ],
            },
        },
    });
