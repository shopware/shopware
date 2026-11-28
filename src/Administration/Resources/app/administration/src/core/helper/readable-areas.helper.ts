/**
 * @sw-package framework
 *
 * Helpers for views that show where something is used across the Administration,
 * for example the media usage sidebar or the tag assignments.
 * Such a view only checks the areas the user may read, so a viewer role does not
 * need read privileges on foreign areas just to show the usage.
 */

type Acl = { can: (privilege: string) => boolean };

/**
 * An area a usage view can check.
 *
 * @private
 */
export type ReadableArea = {
    key: string;
    /**
     * Snippet key of the area name, shown when the area is not checked.
     */
    label: string;
    /**
     * Every entity a check of this area reads.
     * The area only counts as readable when the user may read all of them,
     * because a single unreadable association makes the API reject the whole request.
     */
    entities: readonly string[];
};

/**
 * @private
 */
export function canReadArea(acl: Acl, area: ReadableArea): boolean {
    return area.entities.every((entity) => acl.can(`${entity}:read`));
}

/**
 * @private
 */
export function getReadableAreas<T extends ReadableArea>(acl: Acl, areas: readonly T[]): T[] {
    return areas.filter((area) => canReadArea(acl, area));
}

/**
 * Areas a usage view skips because the user may not read them.
 *
 * @private
 */
export function getUncheckedAreas<T extends ReadableArea>(acl: Acl, areas: readonly T[]): T[] {
    return areas.filter((area) => !canReadArea(acl, area));
}
