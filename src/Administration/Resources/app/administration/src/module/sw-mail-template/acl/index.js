Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'mail_templates',
    roles: {
        viewer: {
            privileges: [
                'mail_template:read',
                'mail_header_footer:read',
                'sales_channel:read',
                'mail_template_media:read',
                'mail_template_type:read',
                'mail_template_sales_channel:read',
                Shopware.Service('privileges').getPrivileges('media.viewer'),
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'mail_template:update',
                'mail_header_footer:update',
                'mail_template_media:create',
                'mail_template_media:delete',
                'mail_template_sales_channel:create',
                'mail_template_sales_channel:delete',
                'sales_channel:update',
                Shopware.Service('privileges').getPrivileges('media.creator'),
            ],
            dependencies: [
                'mail_templates.viewer',
            ],
        },
        creator: {
            privileges: [
                'mail_template:create',
                'mail_header_footer:create',
            ],
            dependencies: [
                'mail_templates.viewer',
                'mail_templates.editor',
            ],
        },
        deleter: {
            privileges: [
                'mail_template:delete',
                'mail_header_footer:delete',
            ],
            dependencies: [
                'mail_templates.viewer',
            ],
        },
    },
});
