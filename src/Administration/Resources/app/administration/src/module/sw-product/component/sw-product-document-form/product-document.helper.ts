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

const selectedMedia = reactive(new WeakMap<Entity<'product_document'>, Entity<'media'>>());

const loadedMedia = reactive(new Map<string, Entity<'media'>>());

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
    const mediaId = getDocumentMediaId(document);
    return selectedMedia.get(document) ?? (mediaId ? loadedMedia.get(mediaId) : undefined);
}

/** @private */
export function setDocumentMedia(document: Entity<'product_document'>, media: Entity<'media'> | null): void {
    if (media) {
        selectedMedia.set(document, media);
    } else {
        selectedMedia.delete(document);
    }
}

/** @private */
export async function loadDocumentMedia(documents: Entity<'product_document'>[]): Promise<void> {
    const { languageId, language, systemLanguageId } = Shopware.Store.get('context').api;
    const languageIds = [languageId, language?.parentId, systemLanguageId];
    const mediaIds = documents.flatMap((document) => [
        getDocumentMediaId(document),
        ...(document.translations ?? [])
            .filter((translation) => languageIds.includes(translation.languageId))
            .map((translation) => translation.mediaId),
    ]);
    const ids = [...new Set(mediaIds.filter((id): id is NonNullable<typeof id> => !!id))];
    if (!ids.length) {
        return;
    }

    const criteria = new Shopware.Data.Criteria(1, ids.length);
    criteria.setIds(ids);
    const media = await Shopware.Service('repositoryFactory').create('media').search(criteria);
    media.forEach((item) => loadedMedia.set(item.id, item));
}

/** @private */
export function getDocumentTitle(document: Entity<'product_document'>): string {
    return document.title || getTranslated(document).title || getDocumentMedia(document)?.fileName || '';
}

/** @private */
export type Translate = (key: string, values?: Record<string, unknown>) => string;

/** @private */
export function getUploadRules() {
    const config = Shopware.Store.get('context').app.config.settings?.productDocuments;
    const allowedMimeTypesByExtension = config?.allowedMimeTypesByExtension ?? {};

    return {
        allowedExtensions: config?.allowedExtensions ?? [],
        allowedMimeTypesByExtension,
        maxFileSize: config?.maxFileSize ?? 0,
        fileAccept: [...new Set(Object.values(allowedMimeTypesByExtension).flat())].join(','),
    };
}

/** @private */
export function getUploadHint(t: Translate): string {
    const { allowedExtensions, maxFileSize } = getUploadRules();

    return t('sw-product.documentForm.uploadHint', {
        extensions: allowedExtensions.join(', ').toUpperCase(),
        size: Shopware.Utils.format.fileSize(maxFileSize),
    });
}

/** @private */
export function isValidDocumentMedia(
    media: Entity<'media'>,
    exceptDocument: Entity<'product_document'> | null,
    t: Translate,
): boolean {
    const { createNotificationError } = useNotification();

    if (media.private) {
        createNotificationError({ message: t('sw-product.documentForm.errorPrivateMedia') });
        return false;
    }

    const { allowedExtensions, maxFileSize } = getUploadRules();
    const extension = media.fileExtension?.toLowerCase() ?? '';
    if (!allowedExtensions.includes(extension) || typeof media.fileSize !== 'number' || media.fileSize > maxFileSize) {
        createNotificationError({ message: getUploadHint(t) });
        return false;
    }

    const documents = Shopware.Store.get('swProductDetail').product.documents ?? [];
    if (documents.some((document) => document !== exceptDocument && getDocumentMediaId(document) === media.id)) {
        createNotificationError({
            message: t('sw-product.documentForm.errorDocumentDuplicated'),
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
    context = Shopware.Context.api,
): Promise<string[]> {
    if (!operations.length) {
        return [];
    }

    const repository = Shopware.Service('repositoryFactory').create('product');
    const { data } = await Shopware.Application.getContainer('init').httpClient.post<{ variantIds: string[] }>(
        `/_action/product/${productId}/document-variants`,
        { operations, dryRun },
        { headers: repository.buildHeaders(context) },
    );

    return data.variantIds;
}
