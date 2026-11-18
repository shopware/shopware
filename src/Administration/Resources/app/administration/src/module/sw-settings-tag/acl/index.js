/**
 * @sw-package inventory
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'tag',
    roles: {
        viewer: {
            // The list and the assignments only show areas the user can read through their own roles
            privileges: ['tag:read'],
            dependencies: [],
        },
        editor: {
            privileges: [
                'tag:update',
                'product_tag:create',
                'product_tag:update',
                'product_tag:delete',
                'category_tag:create',
                'category_tag:update',
                'category_tag:delete',
                'order_tag:create',
                'order_tag:update',
                'order_tag:delete',
                'customer_tag:create',
                'customer_tag:update',
                'customer_tag:delete',
                'media_tag:create',
                'media_tag:update',
                'media_tag:delete',
                'newsletter_recipient_tag:create',
                'newsletter_recipient_tag:update',
                'newsletter_recipient_tag:delete',
                'shipping_method_tag:create',
                'shipping_method_tag:update',
                'shipping_method_tag:delete',
                'landing_page_tag:create',
                'landing_page_tag:update',
                'landing_page_tag:delete',
            ],
            dependencies: ['tag.viewer'],
        },
        creator: {
            privileges: ['tag:create'],
            dependencies: ['tag.viewer', 'tag.editor'],
        },
        deleter: {
            privileges: [
                'tag:delete',
                'product_tag:delete',
                'category_tag:delete',
                'order_tag:delete',
                'customer_tag:delete',
                'media_tag:delete',
                'newsletter_recipient_tag:delete',
                'shipping_method_tag:delete',
                'landing_page_tag:delete',
            ],
            dependencies: ['tag.viewer'],
        },
    },
});
