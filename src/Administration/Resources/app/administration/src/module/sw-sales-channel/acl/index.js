/**
 * @package sales-channel
 *
 * TODO: Full implementation has to be done with NEXT-8925. This contains only some basics to prevent errors on every
 *  page.
 */

Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'sales_channel',
        roles: {
            viewer: {
                privileges: [
                    'sales_channel:read',
                    'sales_channel_type:read',
                    Shopware.Service('privileges').getPrivileges('payment.viewer'),
                    'shipping_method:read',
                    'country:read',
                    'currency:read',
                    'sales_channel_domain:read',
                    'snippet_set:read',
                    'sales_channel_analytics:read',
                    'product_export:read',
                    'theme:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                    'category:read',
                    'customer_group:read',
                    Shopware.Service('privileges').getPrivileges('media.viewer'),
                    'product_export:read',
                    'product_stream:read',
                    'product_visibility:read',
                    'user_config:read',
                    'user_config:create',
                    'user_config:update',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'sales_channel:update',
                    'sales_channel_analytics:create',
                    'sales_channel_analytics:delete',
                    'sales_channel_analytics:update',
                    'sales_channel_country:create',
                    'sales_channel_country:delete',
                    'sales_channel_country:update',
                    'sales_channel_currency:create',
                    'sales_channel_currency:delete',
                    'sales_channel_currency:update',
                    'sales_channel_domain:create',
                    'sales_channel_domain:delete',
                    'sales_channel_domain:update',
                    'sales_channel_language:create',
                    'sales_channel_language:delete',
                    'sales_channel_language:update',
                    'sales_channel_payment_method:create',
                    'sales_channel_payment_method:delete',
                    'sales_channel_payment_method:update',
                    'sales_channel_shipping_method:create',
                    'sales_channel_shipping_method:delete',
                    'sales_channel_shipping_method:update',
                    'theme_sales_channel:create',
                    'theme_sales_channel:delete',
                    'product_export:create',
                    'product_export:update',
                    'product_visibility:create',
                    'product_visibility:delete',
                ],
                dependencies: ['sales_channel.viewer'],
            },
            creator: {
                privileges: [
                    'product_stream:read',
                    'sales_channel:create',
                    'product_export:create',
                    'product_export:update',
                ],
                dependencies: ['sales_channel.viewer', 'sales_channel.editor'],
            },
            deleter: {
                privileges: [
                    'sales_channel:delete',
                    'product_visibility:delete',
                ],
                dependencies: ['sales_channel.viewer'],
            },
        },
    });
