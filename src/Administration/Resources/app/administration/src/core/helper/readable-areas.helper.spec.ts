/**
 * @sw-package framework
 */
import { canReadArea, getReadableAreas, getUncheckedAreas } from 'src/core/helper/readable-areas.helper';

const areas = [
    { key: 'products', label: 'areaProducts', entities: ['product_media', 'product'] },
    { key: 'users', label: 'areaUsers', entities: ['user'] },
    { key: 'categories', label: 'areaCategories', entities: ['category'] },
];

function aclWith(privileges: string[]) {
    return { can: (privilege: string) => privileges.includes(privilege) };
}

describe('src/core/helper/readable-areas.helper', () => {
    it('only counts an area as readable when every entity is readable', () => {
        expect(canReadArea(aclWith(['product_media:read', 'product:read']), areas[0])).toBe(true);
        expect(canReadArea(aclWith(['product:read']), areas[0])).toBe(false);
    });

    it('splits the areas into readable and unchecked ones', () => {
        const acl = aclWith(['user:read', 'category:read', 'product:read']);

        expect(getReadableAreas(acl, areas).map((area) => area.key)).toEqual(['users', 'categories']);
        expect(getUncheckedAreas(acl, areas).map((area) => area.key)).toEqual(['products']);
    });

    it('checks every area for a user with all privileges', () => {
        const acl = { can: () => true };

        expect(getReadableAreas(acl, areas)).toEqual(areas);
        expect(getUncheckedAreas(acl, areas)).toEqual([]);
    });

    it('keeps the additional properties of an area', () => {
        const withAssociations = [{ ...areas[1], associations: ['avatarUsers'] }];

        expect(getReadableAreas(aclWith(['user:read']), withAssociations)[0].associations).toEqual(['avatarUsers']);
    });
});
