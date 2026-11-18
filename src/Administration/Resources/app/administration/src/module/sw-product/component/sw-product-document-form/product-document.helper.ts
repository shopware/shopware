/**
 * @sw-package inventory
 */
import { reactive } from 'vue';
import useNotification from 'src/app/composables/use-notification';

/** @private */
export type DocumentChanges = { title?: string | null; mediaId?: EntityKey<'media'> | null };

/** @private */
export type DocumentVariantOperation = { mediaId: string; title: string | null; changes?: DocumentChanges };

type DocumentTranslated = { title?: string | null; mediaId?: EntityKey<'media'> | null };

const selectedMedia = reactive(new WeakMap<Entity<'product_document'>, Entity<'media'> | null>());

/** @private */
export function getTranslated(document: Entity<'product_document'>): DocumentTranslated {
    return (document.translated ?? {}) as DocumentTranslated;
}

/** @private */
export function getDocumentMediaId(document: Entity<'product_document'>): EntityKey<'media'> | null {
    return document.mediaId || getTranslated(document).mediaId || null;
}

/** @private */
export function getDocumentMedia(document: Entity<'product_document'>): Entity<'media'> | null | undefined {
    if (selectedMedia.has(document)) {
        return selectedMedia.get(document);
    }

    const mediaId = getDocumentMediaId(document);
    return mediaId ? document.translations?.find((translation) => translation.mediaId === mediaId)?.media : undefined;
}

/** @private */
export function setDocumentMedia(document: Entity<'product_document'>, media: Entity<'media'> | null): void {
    selectedMedia.set(document, media);
}

/** @private */
export function getDocumentTitle(document: Entity<'product_document'>): string {
    return document.title || getTranslated(document).title || getDocumentMedia(document)?.fileName || '';
}

/** @private */
export const allowedExtensions = [
    'pdf',
    'docx',
    'jpg',
    'jpeg',
    'png',
];

/** @private */
export const maxFileSize = 20 * 1024 * 1024;

/** @private */
export function getUploadHint(): string {
    return Shopware.Snippet.t('sw-product.documentForm.uploadHint', {
        extensions: 'PDF, DOCX, JPG',
        lastExtension: 'PNG',
        size: maxFileSize / (1024 * 1024),
    }) as string;
}

/** @private */
export function getFileAccept(extensionByType: Record<string, string[]>): string {
    return Object.entries(extensionByType)
        .filter(([, extensions]) => extensions.some((extension) => allowedExtensions.includes(extension)))
        .map(([mimeType]) => mimeType)
        .join(',');
}

/** @private */
export function isValidDocumentMedia(media: Entity<'media'>, exceptDocument: Entity<'product_document'> | null): boolean {
    const { createNotificationError } = useNotification();

    if (media.private) {
        createNotificationError({ message: Shopware.Snippet.t('sw-product.documentForm.errorPrivateMedia') as string });
        return false;
    }

    const extension = media.fileExtension?.toLowerCase() ?? '';
    if (!allowedExtensions.includes(extension) || typeof media.fileSize !== 'number' || media.fileSize > maxFileSize) {
        createNotificationError({ message: getUploadHint() });
        return false;
    }

    const documents = Shopware.Store.get('swProductDetail').product.documents ?? [];
    if (documents.some((document) => document !== exceptDocument && getDocumentMediaId(document) === media.id)) {
        createNotificationError({
            message: Shopware.Snippet.t('sw-product.documentForm.errorDocumentDuplicated') as string,
        });
        return false;
    }

    return true;
}

/** @private */
export function getFallbackTranslation(
    document: Entity<'product_document'>,
    field: 'title' | 'mediaId',
): Entity<'product_document_translation'> | undefined {
    const { language, systemLanguageId } = Shopware.Store.get('context').api;

    return [language?.parentId, systemLanguageId]
        .map((languageId) => document.translations?.find((translation) => translation.languageId === languageId))
        .find((translation) => translation && translation[field] !== null);
}

/** @private */
export async function syncDocumentVariants(
    productId: string,
    operations: DocumentVariantOperation[],
    dryRun = false,
): Promise<string[]> {
    if (!operations.length) {
        return [];
    }

    const repository = Shopware.Service('repositoryFactory').create('product');
    const { data } = await Shopware.Application.getContainer('init').httpClient.post<{ variantIds: string[] }>(
        `/_action/product/${productId}/document-variants`,
        { operations, dryRun },
        { headers: repository.buildHeaders() },
    );

    return data.variantIds;
}
