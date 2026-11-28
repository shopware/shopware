/**
 * @sw-package discovery
 */
import type { ReadableArea } from 'src/core/helper/readable-areas.helper';
import { canReadArea, getReadableAreas, getUncheckedAreas } from 'src/core/helper/readable-areas.helper';

type Acl = { can: (privilege: string) => boolean };

/**
 * An area that can use a media item.
 * See `ReadableArea` for when an area counts as readable.
 *
 * @private
 */
export type MediaUsageArea = ReadableArea & {
    /**
     * Associations on `media` the usage sidebar reads.
     */
    associations: string[];
};

/**
 * @private
 */
export const MEDIA_USAGE_AREAS: MediaUsageArea[] = [
    {
        key: 'products',
        label: 'sw-media.sidebar.usage.areaProducts',
        associations: ['productMedia.product'],
        entities: [
            'product_media',
            'product',
            'product_document',
            'product_document_translation',
        ],
    },
    {
        key: 'categories',
        label: 'sw-media.sidebar.usage.areaCategories',
        associations: ['categories'],
        entities: ['category'],
    },
    {
        key: 'landingPages',
        label: 'sw-media.sidebar.usage.areaLandingPages',
        associations: [],
        entities: ['landing_page'],
    },
    {
        key: 'manufacturers',
        label: 'sw-media.sidebar.usage.areaManufacturers',
        associations: ['productManufacturers.products'],
        entities: [
            'product_manufacturer',
            'product',
        ],
    },
    {
        key: 'mailTemplates',
        label: 'sw-media.sidebar.usage.areaMailTemplates',
        associations: ['mailTemplateMedia.mailTemplate'],
        entities: [
            'mail_template_media',
            'mail_template',
        ],
    },
    {
        key: 'documents',
        label: 'sw-media.sidebar.usage.areaDocuments',
        associations: ['documentBaseConfigs'],
        entities: ['document_base_config'],
    },
    {
        key: 'users',
        label: 'sw-media.sidebar.usage.areaUsers',
        associations: ['avatarUsers'],
        entities: ['user'],
    },
    {
        key: 'paymentMethods',
        label: 'sw-media.sidebar.usage.areaPaymentMethods',
        associations: ['paymentMethods'],
        entities: ['payment_method'],
    },
    {
        key: 'shippingMethods',
        label: 'sw-media.sidebar.usage.areaShippingMethods',
        associations: ['shippingMethods'],
        entities: ['shipping_method'],
    },
    {
        key: 'layouts',
        label: 'sw-media.sidebar.usage.areaLayouts',
        associations: [
            'cmsBlocks.section.page',
            'cmsSections.page',
            'cmsPages',
        ],
        entities: [
            'cms_page',
            'cms_section',
            'cms_block',
            'cms_slot',
        ],
    },
];

/**
 * @private
 */
export function canReadMediaUsageArea(acl: Acl, key: string): boolean {
    const area = MEDIA_USAGE_AREAS.find((candidate) => candidate.key === key);

    return !!area && canReadArea(acl, area);
}

/**
 * Areas whose usage is not checked because the user may not read them.
 *
 * @private
 */
export function getUnreadableMediaUsageAreas(acl: Acl): MediaUsageArea[] {
    return getUncheckedAreas(acl, MEDIA_USAGE_AREAS);
}

/**
 * Associations to load with a media item, limited to the areas the user may read.
 *
 * @private
 */
export function getReadableMediaUsageAssociations(acl: Acl): string[] {
    const associations = getReadableAreas(acl, MEDIA_USAGE_AREAS).flatMap((area) => area.associations);

    if (acl.can('tag:read')) {
        associations.unshift('tags');
    }

    return associations;
}
