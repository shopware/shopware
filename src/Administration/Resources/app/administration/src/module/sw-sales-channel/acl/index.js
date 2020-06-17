/**
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
                    'payment_method:read',
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
                    'customer_group:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: ['sales_channel:write'],
                dependencies: ['sales_channel.viewer']
            },
            creator: {
                privileges: [
                    'product_stream:read'
                ],
                dependencies: ['sales_channel.viewer', 'sales_channel.editor']
            },
            deleter: {
                privileges: [],
                dependencies: ['sales_channel.viewer']
            }
        }
    });
