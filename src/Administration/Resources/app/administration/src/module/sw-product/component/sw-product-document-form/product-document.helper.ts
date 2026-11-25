/**
 * @sw-package inventory
 */
import { reactive } from 'vue';

const selectedMedia = reactive(new WeakMap<Entity<'product_document'>, Entity<'media'>>());

/** @private */
export function getDocumentMedia(document: Entity<'product_document'>): Entity<'media'> | undefined {
    const mediaId = document.translated?.mediaId;
    return (
        selectedMedia.get(document) ?? document.translations?.find((translation) => translation.mediaId === mediaId)?.media
    );
}

/** @private */
export function setDocumentMedia(document: Entity<'product_document'>, media: Entity<'media'> | null): void {
    if (media) {
        selectedMedia.set(document, media);
    } else {
        selectedMedia.delete(document);
    }
}
