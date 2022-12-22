/**
 * @package content
 */
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'content',
        key: 'media',
        roles: {
            viewer: {
                privileges: [
                    'media:read',
                    'media_folder:read',
                    'media_default_folder:read',
                    'media_thumbnail_size:read',
                    'media_folder_configuration:read',
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
                    'cms_page:read',
                    'cms_section:read',
                    'cms_block:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'media:update',
                    'media_folder:update',
                    'media_default_folder:update',
                    'media_folder_configuration:create',
                    'media_folder_configuration:update',
                    'media_folder_configuration:delete',
                    'media_folder:delete',
                    'media_thumbnail_size:create',
                    'media_thumbnail_size:update',
                    'media_thumbnail_size:delete',
                    'media_folder_configuration_media_thumbnail_size:delete',
                    'media_folder_configuration_media_thumbnail_size:create',
                    'media_folder_configuration_media_thumbnail_size:update',
                ],
                dependencies: [
                    'media.viewer',
                ],
            },
            creator: {
                privileges: [
                    'media:create',
                    'media_folder:create',
                    'media_default_folder:create',
                ],
                dependencies: [
                    'media.viewer',
                    'media.editor',
                ],
            },
            deleter: {
                privileges: [
                    'media:delete',
                    'media_folder:delete',
                    'media_default_folder:delete',
                ],
                dependencies: [
                    'media.viewer',
                ],
            },
        },
    });
