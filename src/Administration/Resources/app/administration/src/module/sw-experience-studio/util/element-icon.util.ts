/**
 * @sw-package discovery
 */

type LocalElementIcon = {
    assetPath: string;
    meteorIcon: string;
};

const localElementIcons: Record<string, LocalElementIcon> = {
    'content-text': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/content-text.svg',
        meteorIcon: 'regular-align-left',
    },
    'filter-panel': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/filter-panel.svg',
        meteorIcon: 'regular-filter-s',
    },
    'grid-container': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/grid-container.svg',
        meteorIcon: 'regular-view-grid',
    },
    'media-gallery': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/media-gallery.svg',
        meteorIcon: 'image-text',
    },
    'media-image': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/media-image.svg',
        meteorIcon: 'regular-image',
    },
    'media-vimeo': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/media-vimeo.svg',
        meteorIcon: 'regular-vimeo',
    },
    'media-youtube': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/media-youtube.svg',
        meteorIcon: 'regular-youtube',
    },
    'navigation-breadcrumb': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/navigation-breadcrumb.svg',
        meteorIcon: 'double-chevron-right-s',
    },
    'navigation-tree': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/navigation-tree.svg',
        meteorIcon: 'regular-sitemap',
    },
    'product-listing': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-listing.svg',
        meteorIcon: 'regular-products',
    },
    'product-manufacturer': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-manufacturer.svg',
        meteorIcon: 'regular-factory',
    },
    'product-price': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-price.svg',
        meteorIcon: 'regular-tag',
    },
    'product-quantity': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-quantity.svg',
        meteorIcon: 'regular-list-numbered-xs',
    },
    'product-review-filters': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-review-filters.svg',
        meteorIcon: 'regular-filter-s',
    },
    'product-review-form': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-review-form.svg',
        meteorIcon: 'regular-pencil-s',
    },
    'product-review-summary': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-review-summary.svg',
        meteorIcon: 'regular-star-half',
    },
    'product-reviews': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-reviews.svg',
        meteorIcon: 'regular-star',
    },
    'product-slider': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-slider.svg',
        meteorIcon: 'regular-bulk-edit',
    },
    'product-variants': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/product-variants.svg',
        meteorIcon: 'regular-variants',
    },
    'tabs-panel': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/tabs-panel.svg',
        meteorIcon: 'regular-window',
    },
    'tabs-tab': {
        assetPath: '/administration/administration/static/img/experience-studio/element-icons/tabs-tab.svg',
        meteorIcon: 'regular-folder',
    },
};

/**
 * @private
 */
export function getLocalElementIcon(icon: string | null): LocalElementIcon | null {
    if (!icon?.startsWith('local:')) {
        return null;
    }

    const key = icon.slice('local:'.length);

    return Object.hasOwn(localElementIcons, key) ? localElementIcons[key] : null;
}

/**
 * @private
 */
export function getMeteorElementIcon(icon: string | null, fallback = 'regular-square'): string {
    if (icon?.startsWith('local:')) {
        return getLocalElementIcon(icon)?.meteorIcon ?? fallback;
    }

    return icon || fallback;
}
