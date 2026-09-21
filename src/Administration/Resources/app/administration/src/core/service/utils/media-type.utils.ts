/**
 * @sw-package discovery
 *
 * @module core/service/utils/media-type
 */

interface TypedMedia {
    mediaType?: {
        name?: string;
    } | null;
}

/**
 * Media of these types never carries a file. A missing file is their intended state, so the UI must
 * not present it as a broken or incomplete upload.
 *
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export const FILELESS_MEDIA_TYPES: readonly string[] = ['SPATIAL_SCENE'];

/**
 * Media of these types stand for something other than their file: a spatial scene is represented by
 * a media whose file is a render of that scene. The UI therefore names such media after themselves
 * instead of putting the file they happen to carry in front of them.
 *
 * Overlaps with {@link FILELESS_MEDIA_TYPES} today, but the two say different things - a scene has
 * no render until it has been saved once, which is what that list is about.
 *
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export const REPRESENTATIVE_MEDIA_TYPES: readonly string[] = ['SPATIAL_SCENE'];

/**
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export function isRepresentativeMediaType(media?: TypedMedia | null): boolean {
    const typeName = media?.mediaType?.name;

    return typeName !== undefined && REPRESENTATIVE_MEDIA_TYPES.includes(typeName);
}

/**
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export function isFilelessMediaType(media?: TypedMedia | null): boolean {
    const typeName = media?.mediaType?.name;

    return typeName !== undefined && FILELESS_MEDIA_TYPES.includes(typeName);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    FILELESS_MEDIA_TYPES,
    isFilelessMediaType,
    REPRESENTATIVE_MEDIA_TYPES,
    isRepresentativeMediaType,
};
