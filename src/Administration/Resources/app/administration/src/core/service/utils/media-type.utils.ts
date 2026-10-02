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

interface FilelessMediaTypeOptions {
    /**
     * Shown in place of a preview while the media has no file. Without one the media gets the
     * neutral file icon, which is still better than the broken-upload icon it would get otherwise.
     */
    placeholderIcon?: string;
}

/**
 * Media types whose media may exist without a file. A missing file is their intended state, so the
 * UI must not present it as a broken or incomplete upload.
 */
const filelessMediaTypes = new Map<string, FilelessMediaTypeOptions>();

/**
 * Media types that stand for something other than their file - a media whose file is only a
 * rendering of the thing it represents. The UI names such media after themselves instead of putting
 * the file they happen to carry in front of them.
 *
 * Overlaps with the fileless types in practice, but the two say different things: one is about a
 * file that may be absent, the other about a file that is not the point.
 */
const representativeMediaTypes = new Set<string>();

/**
 * Both registries start out empty. The media types that belong in them come from extensions, which
 * register their own on boot - the platform has no such type of its own.
 *
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export function registerFilelessMediaType(typeName: string, options: FilelessMediaTypeOptions = {}): void {
    filelessMediaTypes.set(typeName, options);
}

/**
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export function registerRepresentativeMediaType(typeName: string): void {
    representativeMediaTypes.add(typeName);
}

/**
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export function isFilelessMediaType(media?: TypedMedia | null): boolean {
    const typeName = media?.mediaType?.name;

    return typeName !== undefined && filelessMediaTypes.has(typeName);
}

/**
 * The icon the registering extension asked for, or undefined when it named none.
 *
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export function filelessMediaTypeIcon(media?: TypedMedia | null): string | undefined {
    const typeName = media?.mediaType?.name;

    return typeName === undefined ? undefined : filelessMediaTypes.get(typeName)?.placeholderIcon;
}

/**
 * @private
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export function isRepresentativeMediaType(media?: TypedMedia | null): boolean {
    const typeName = media?.mediaType?.name;

    return typeName !== undefined && representativeMediaTypes.has(typeName);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    registerFilelessMediaType,
    isFilelessMediaType,
    filelessMediaTypeIcon,
    registerRepresentativeMediaType,
    isRepresentativeMediaType,
};
