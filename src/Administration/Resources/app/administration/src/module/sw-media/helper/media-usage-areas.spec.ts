/**
 * @sw-package discovery
 */
import {
    MEDIA_USAGE_AREAS,
    canReadMediaUsageArea,
    getReadableMediaUsageAssociations,
    getUnreadableMediaUsageAreas,
} from './media-usage-areas';

function aclWith(privileges: string[]) {
    return { can: (privilege: string) => privileges.includes(privilege) };
}

const allReads = [...new Set(MEDIA_USAGE_AREAS.flatMap((area) => area.entities))].map((entity) => `${entity}:read`);

describe('module/sw-media/helper/media-usage-areas', () => {
    it('needs a read on every entity of an area', () => {
        expect(canReadMediaUsageArea(aclWith(['product:read']), 'products')).toBe(false);
        expect(
            canReadMediaUsageArea(
                aclWith([
                    'product:read',
                    'product_media:read',
                ]),
                'products',
            ),
        ).toBe(false);
        expect(
            canReadMediaUsageArea(
                aclWith([
                    'product:read',
                    'product_media:read',
                    'product_document:read',
                    'product_document_translation:read',
                ]),
                'products',
            ),
        ).toBe(true);
    });

    it('treats an unknown area as unreadable', () => {
        expect(canReadMediaUsageArea(aclWith(allReads), 'unknown')).toBe(false);
    });

    it('lists the areas the user may not read', () => {
        const unreadable = getUnreadableMediaUsageAreas(
            aclWith([
                'category:read',
                'user:read',
            ]),
        ).map((area) => area.key);

        expect(unreadable).not.toContain('categories');
        expect(unreadable).not.toContain('users');
        expect(unreadable).toHaveLength(MEDIA_USAGE_AREAS.length - 2);
    });

    it('returns no unreadable areas when every entity is readable', () => {
        expect(getUnreadableMediaUsageAreas(aclWith(allReads))).toEqual([]);
    });

    it('only returns associations of readable areas', () => {
        expect(
            getReadableMediaUsageAssociations(
                aclWith([
                    'category:read',
                    'cms_page:read',
                ]),
            ),
        ).toEqual(['categories']);
    });

    it('adds tags first when the user may read tags', () => {
        expect(
            getReadableMediaUsageAssociations(
                aclWith([
                    'tag:read',
                    'user:read',
                ]),
            ),
        ).toEqual([
            'tags',
            'avatarUsers',
        ]);
    });

    it('returns every association with all reads', () => {
        expect(getReadableMediaUsageAssociations(aclWith(['tag:read', ...allReads]))).toEqual([
            'tags',
            'productMedia.product',
            'categories',
            'productManufacturers.products',
            'mailTemplateMedia.mailTemplate',
            'documentBaseConfigs',
            'avatarUsers',
            'paymentMethods',
            'shippingMethods',
            'cmsBlocks.section.page',
            'cmsSections.page',
            'cmsPages',
        ]);
    });
});
