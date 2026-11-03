/**
 * @private
 * The name the thumbnailSize filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const thumbnailSizeFilterName = 'thumbnailSize';

/**
 * @sw-package discovery
 */
Shopware.Filter.register(thumbnailSizeFilterName, (value: Entity<'media_thumbnail_size'>) => {
    if (!value || !(value.getEntityName() === 'media_thumbnail_size')) {
        return '';
    }

    if (!value.width || !value.height) {
        return '';
    }

    return `${value.width}x${value.height}`;
});

/* @private */
export {};
