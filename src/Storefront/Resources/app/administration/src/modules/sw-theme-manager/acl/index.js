/**
 * @package sales-channel
 */

Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'content',
        key: 'theme',
        roles: {
            viewer: {
                privileges: [
                    'theme:read',
                    'theme_child:read',
                    'sales_channel:read',
                    Shopware.Service('privileges').getPrivileges('media.viewer')
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'theme:update',
                    'theme_child:update',
                    'tag:read',
                    'product_media:read',
                    'product:read',
                    'category:read',
                    'product_manufacturer:read',
                    'mail_template_media:read',
                    'mail_template:read',
                    'document_base_config:read',
                    'user:read',
                    'payment_method:read',
                    'shipping_method:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                    Shopware.Service('privileges').getPrivileges('media.creator')
                ],
                dependencies: [
                    'theme.viewer'
                ]
            },
            creator: {
                privileges: [
                    'theme:create',
                    'theme_child:create'
                ],
                dependencies: [
                    'theme.viewer',
                    'theme.editor'
                ]
            },
            deleter: {
                privileges: [
                    'theme:delete',
                    'theme_child:delete'
                ],
                dependencies: [
                    'theme.viewer'
                ]
            }
        }
    });
