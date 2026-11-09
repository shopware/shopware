/**
 * @sw-package inventory
 */

/** @private */
export function isInvalidDocumentFile(media: Entity<'media'>, extensions: string[], maxFileSize: number): boolean {
    return (
        !extensions.includes(media.fileExtension?.toLowerCase() ?? '') ||
        typeof media.fileSize !== 'number' ||
        media.fileSize > maxFileSize
    );
}

/** @private */
export function getFileAccept(extensionByType: Record<string, string[]>, allowedExtensions: string[]): string {
    return Object.entries(extensionByType)
        .filter(([, extensions]) => extensions.some((extension) => allowedExtensions.includes(extension)))
        .map(([mimeType]) => mimeType)
        .join(',');
}

/** @private */
export function getHintParams(allowedExtensions: string[], maxFileSize: number) {
    const extensions = allowedExtensions.filter((extension) => extension !== 'jpeg');
    return {
        extensions: extensions.slice(0, -1).join(', ').toUpperCase(),
        lastExtension: extensions.at(-1)?.toUpperCase(),
        size: maxFileSize / (1024 * 1024),
    };
}
