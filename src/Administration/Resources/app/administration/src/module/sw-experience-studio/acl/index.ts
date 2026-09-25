/**
 * @sw-package discovery
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'experience_studio',
    roles: {
        viewer: {
            privileges: [
                'content_layout:read',
                'property_group:read',
                'sales_channel:read',
                'product:read',
                'category:read',
                'product_content_layout:read',
                'category_content_layout:read',
                'landing_page_content_layout:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'content_layout:update',
                'product_content_layout:create',
                'product_content_layout:update',
                'product_content_layout:delete',
                'category_content_layout:create',
                'category_content_layout:update',
                'category_content_layout:delete',
            ],
            dependencies: [
                'experience_studio.viewer',
            ],
        },
        creator: {
            privileges: [
                'content_layout:create',
            ],
            dependencies: [
                'experience_studio.viewer',
                'experience_studio.editor',
            ],
        },
        deleter: {
            privileges: [
                'content_layout:delete',
            ],
            dependencies: [
                'experience_studio.viewer',
            ],
        },
    },
});
