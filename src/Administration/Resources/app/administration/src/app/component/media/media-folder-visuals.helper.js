/**
 * @private
 * @sw-package discovery
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export const productFolderEntities = [
    'category',
    'product',
    'product_download',
    'product_manufacturer',
    'product_stream',
    'property_group',
];

export const contentFolderEntities = [
    'cms_page',
    'spatial_scene',
    'theme',
];

export const neutralFolderEntities = [
    'document',
    'import_export_profile',
    'mail_template',
    'payment_method',
    'shipping_method',
];

export const defaultFolderIconNames = {
    ai_generated: 'solid-sparkles',
    category: 'regular-products',
    cms_page: 'regular-content',
    document: 'regular-file-text',
    import_export_profile: 'regular-database',
    mail_template: 'regular-envelope',
    payment_method: 'regular-credit-card',
    product: 'regular-products',
    product_download: 'regular-products',
    product_manufacturer: 'regular-products',
    shipping_method: 'regular-truck',
    spatial_scene: 'regular-content',
    user: 'regular-user',
};

export const folderIconColors = {
    blue: '#189EFF',
    green: '#57D9A3',
    grey: '#758CA3',
    pink: '#FF85C2',
};

export function normalizeIconName(iconName) {
    return iconName.replace(/^solid-/, 'regular-');
}

export function getFolderColorFamily(folderEntity, iconName) {
    if (productFolderEntities.includes(folderEntity)) {
        return 'green';
    }

    if (contentFolderEntities.includes(folderEntity)) {
        return 'pink';
    }

    if (neutralFolderEntities.includes(folderEntity)) {
        return 'grey';
    }

    switch (folderEntity) {
        case 'ai_generated':
        case 'user':
            return 'blue';
        default:
            break;
    }

    switch (iconName) {
        case 'regular-box':
        case 'regular-products':
            return 'green';
        case 'regular-content':
            return 'pink';
        case 'regular-cog':
        case 'regular-database':
        case 'regular-file-text':
        case 'regular-envelope':
        case 'regular-credit-card':
        case 'regular-truck':
            return 'grey';
        default:
            return 'blue';
    }
}

export function getFolderThumbnailName(folderEntity, iconName) {
    if (productFolderEntities.includes(folderEntity)) {
        return 'multicolor-folder-thumbnail--green';
    }

    if (contentFolderEntities.includes(folderEntity)) {
        return 'multicolor-folder-thumbnail--pink';
    }

    if (neutralFolderEntities.includes(folderEntity)) {
        return 'multicolor-folder-thumbnail--grey';
    }

    if (folderEntity === 'user') {
        return 'multicolor-folder-thumbnail';
    }

    switch (iconName) {
        case 'regular-box':
        case 'regular-products':
            return 'multicolor-folder-thumbnail--green';
        case 'regular-database':
        case 'regular-cog':
            return 'multicolor-folder-thumbnail--grey';
        case 'regular-content':
            return 'multicolor-folder-thumbnail--pink';
        default:
            return 'multicolor-folder-thumbnail';
    }
}
