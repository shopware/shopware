/**
 * @sw-package inventory
 */
import type EntityFactory from 'src/core/data/entity-factory.data';
import type Repository from 'src/core/data/repository.data';

type MediaResolver = (document: Entity<'product_document'> | null) => Entity<'media'> | undefined;

/** @private */
export type DocumentChanges = {
    title?: string | null;
    mediaId?: EntityKey<'media'> | null;
    translated?: Partial<ReturnType<typeof getDocumentTranslationResetChanges>['translated']>;
};

/** @private */
export function getDocumentTranslationResetChanges(
    document: Entity<'product_document'>,
    language: { parentId?: string | null } | null,
    systemLanguageId: string | null,
) {
    const fallbackLanguageIds = [language?.parentId, systemLanguageId];
    const translations = fallbackLanguageIds.flatMap(
        (languageId) => document.translations?.filter((translation) => translation.languageId === languageId) || [],
    );
    return {
        title: null,
        mediaId: null,
        translated: {
            ...document.translated,
            title: translations.find((translation) => translation.title !== null)?.title ?? null,
            mediaId: translations.find((translation) => translation.mediaId !== null)?.mediaId ?? null,
        },
    };
}

/** @private */
export function getDocumentTranslationChanges(
    document: Entity<'product_document'>,
    changes: DocumentChanges,
    context: Pick<typeof Shopware.Context.api, 'language' | 'systemLanguageId'>,
    useTranslatedValues = false,
): DocumentChanges {
    const result: DocumentChanges = {};
    const original = useTranslatedValues
        ? {
              title: (document.title ?? document.translated?.title ?? '').trim(),
              mediaId: document.mediaId || document.translated?.mediaId,
          }
        : document;
    if (changes.title !== undefined && changes.title !== (original.title ?? null)) {
        result.title = changes.title;
    }
    if (changes.mediaId !== undefined && changes.mediaId !== (original.mediaId ?? null)) {
        result.mediaId = changes.mediaId;
    }
    const resetFields = (['title', 'mediaId'] as const).filter((field) => result[field] === null);
    if (resetFields.length) {
        const fallback = getDocumentTranslationResetChanges(document, context.language, context.systemLanguageId).translated;
        result.translated = {
            ...document.translated,
            ...Object.fromEntries(resetFields.map((field) => [field, fallback[field]])),
        };
    }
    return result;
}

/** @private */
export function getDocumentEditChanges(
    document: Entity<'product_document'>,
    editor: {
        documentTitle: string;
        replacementMedia: Pick<Entity<'media'>, 'id'> | null;
        editingMedia?: Pick<Entity<'media'>, 'id'>;
        isDocumentFileRemoved: boolean;
        isSystemDefaultLanguage: boolean;
    },
    displayTitle: string,
    context: Pick<typeof Shopware.Context.api, 'language' | 'systemLanguageId'>,
) {
    const title = editor.documentTitle.trim();
    const shouldUpdateTitle = !editor.isSystemDefaultLanguage || title !== displayTitle.trim();
    const changes = getDocumentTranslationChanges(
        document,
        {
            title: shouldUpdateTitle ? title || null : undefined,
            mediaId: editor.replacementMedia?.id ?? (editor.isDocumentFileRemoved ? null : undefined),
        },
        context,
    );
    return {
        changes,
        variantChanges: editor.isSystemDefaultLanguage ? { title, mediaId: editor.editingMedia?.id } : changes,
    };
}

function getMatchingVariantDocuments(variant: Entity<'product'>, original: Entity<'product_document'>) {
    const mediaId = original.mediaId || original.translated?.mediaId;
    const title = original.title || original.translated?.title || '';
    return variant.documents?.filter(
        (document) =>
            document.productId === variant.id &&
            (document.mediaId || document.translated?.mediaId) === mediaId &&
            (document.title || document.translated?.title || '') === title,
    );
}

function getDocumentVariantCandidates(
    variants: Entity<'product'>[],
    children: EntityCollection<'product'>,
    original: Entity<'product_document'>,
) {
    const candidates = new Map(variants.map((variant) => [variant.id, variant]));
    children.forEach((variant) => candidates.set(variant.id, variant));
    return Array.from(candidates.values()).flatMap((variant) => {
        const documents = variant.documents;
        const matching = getMatchingVariantDocuments(variant, original);
        return documents && matching?.length ? [{ variant, documents, matching }] : [];
    });
}

function refreshDocumentPositions(documents: EntityCollection<'product_document'>): void {
    documents.forEach((document, index) => {
        document.position = index;
    });
}

/** @private */
export function applyDocumentChanges(
    document: Entity<'product_document'>,
    changes: DocumentChanges,
    context: typeof Shopware.Context.api,
    getMedia: MediaResolver,
): void {
    Object.assign(document, changes);
    const translation = document.translations?.find((entry) => entry.languageId === context.languageId);
    if (!translation?.isNew()) {
        return;
    }
    if (changes.title !== undefined) {
        Object.assign(translation, { title: changes.title });
    }
    if (changes.mediaId !== undefined) {
        const media = changes.mediaId ? getMedia(document) : null;
        Object.assign(translation, { mediaId: changes.mediaId, media });
    }
}

function resetDocumentLanguageVersion(
    document: Entity<'product_document'>,
    context: typeof Shopware.Context.api,
    getMedia: MediaResolver,
): void {
    applyDocumentChanges(
        document,
        getDocumentTranslationResetChanges(document, context.language, context.systemLanguageId),
        context,
        getMedia,
    );
}

/** @private */
export function stageVariantDocumentChanges(
    product: Entity<'product'>,
    variants: Entity<'product'>[],
    original: Entity<'product_document'>,
    {
        changes,
        context,
        getMedia,
    }: { changes: DocumentChanges; context: typeof Shopware.Context.api; getMedia: MediaResolver },
): string[] {
    const children = product.children || new Shopware.Data.EntityCollection('/product', 'product', context);
    const candidates = getDocumentVariantCandidates(variants, children, original);
    const updatedVariantIds: string[] = [];
    candidates.forEach(({ variant, matching }) => {
        let hasChanges = false;
        matching.forEach((copy) => {
            const update = getDocumentTranslationChanges(
                copy,
                changes,
                context,
                context.languageId === context.systemLanguageId,
            );
            const { title, mediaId } = copy;
            applyDocumentChanges(copy, update, context, getMedia);
            if (title !== copy.title || mediaId !== copy.mediaId) {
                hasChanges = true;
            }
        });
        if (hasChanges) {
            if (!children.has(variant.id)) {
                children.add(variant);
            }
            updatedVariantIds.push(variant.id);
        }
    });
    product.children = children;
    return updatedVariantIds;
}

function stageVariantDocumentRemovals(
    product: Entity<'product'>,
    variants: Entity<'product'>[],
    original: Entity<'product_document'>,
    { context, getMedia }: { context: typeof Shopware.Context.api; getMedia: MediaResolver },
): string[] {
    const children = product.children || new Shopware.Data.EntityCollection('/product', 'product', context);
    const origin = product.getOrigin();
    const originChildren = origin.children || new Shopware.Data.EntityCollection('/product', 'product', context);
    const candidates = getDocumentVariantCandidates(variants, children, original);
    candidates.forEach(({ variant, documents, matching }) => {
        // Register loaded children in the origin so the changeset includes nested document deletions.
        if (!variant.isNew() && !originChildren.has(variant.id)) {
            originChildren.add(variant);
        }
        if (!children.has(variant.id)) {
            children.add(variant);
        }
        if (context.languageId === context.systemLanguageId) {
            matching.forEach((copy) => documents.remove(copy.id));
            refreshDocumentPositions(documents);
        } else {
            matching.forEach((copy) => resetDocumentLanguageVersion(copy, context, getMedia));
        }
    });
    product.children = children;
    origin.children = originChildren;
    return candidates.map(({ variant }) => variant.id);
}

/** @private */
export function copyInheritedDocuments(
    inheritedDocuments: EntityCollection<'product_document'>,
    productId: EntityKey<'product'>,
    documentRepository: Repository<'product_document'>,
    entityFactory: EntityFactory,
): Entity<'product_document'>[] {
    const documents: Entity<'product_document'>[] = [];
    inheritedDocuments.forEach((source) => {
        const document = documentRepository.create(Shopware.Context.api);
        document.productId = productId;
        document.position = source.position;
        document.title = source.title;
        document.mediaId = source.mediaId;
        document.customFields = Shopware.Utils.object.cloneDeep(source.customFields);
        document.translated = Shopware.Utils.object.cloneDeep(source.translated);

        source.translations?.forEach((sourceTranslation) => {
            const translation = entityFactory.create(
                'product_document_translation',
                null,
                Shopware.Context.api,
            ) as Entity<'product_document_translation'>;
            translation.productDocumentId = document.id;
            translation.languageId = sourceTranslation.languageId;
            translation.mediaId = sourceTranslation.mediaId;
            translation.title = sourceTranslation.title;
            translation.customFields = Shopware.Utils.object.cloneDeep(sourceTranslation.customFields);
            document.translations.add(translation);
            translation.media = sourceTranslation.media;
        });
        documents.push(document);
    });
    return documents;
}

/** @private */
export function getDocumentValidationError(
    media: Entity<'media'>,
    editingDocument: Entity<'product_document'> | null,
    {
        documents,
        allowedExtensions,
        maxFileSize,
    }: {
        documents: EntityCollection<'product_document'>;
        allowedExtensions: string[];
        maxFileSize: number;
    },
): string | null {
    if (media.private) {
        return 'sw-product.documentForm.errorPrivateMedia';
    }
    const isAllowedExtension = allowedExtensions.includes(media.fileExtension?.toLowerCase() ?? '');
    if (!isAllowedExtension || typeof media.fileSize !== 'number' || media.fileSize > maxFileSize) {
        return 'uploadHint';
    }
    const isDuplicate = documents.some(
        (document) => document !== editingDocument && (document.mediaId || document.translated?.mediaId) === media.id,
    );
    return isDuplicate ? 'sw-product.documentForm.errorDocumentDuplicated' : null;
}

/** @private */
export function getDocumentRemovalMessage(
    documentsCount: number,
    isSystemDefaultLanguage: boolean,
    { name, language, count }: { name: string; language: string; count: number },
) {
    if (documentsCount > 1) {
        return {
            key: isSystemDefaultLanguage
                ? 'sw-product.documentForm.bulkRemoveSuccess'
                : 'sw-product.documentForm.bulkRemoveLanguageVersionSuccess',
            params: { count: documentsCount, language },
        };
    }
    return isSystemDefaultLanguage
        ? { key: 'sw-product.documentForm.removeSuccess', params: { name, count } }
        : { key: 'sw-product.documentForm.removeLanguageVersionSuccess', params: { language, name } };
}

/** @private */
export function getDocumentContentLanguageName(language: Entity<'language'> | null, currentLocale: string | null): string {
    const code = language?.locale?.code.split('-')[0];
    const locale = currentLocale || 'en-GB';
    const names = new Intl.DisplayNames([locale], { type: 'language' });
    return code ? names.of(code) || '' : language?.name || '';
}

/** @private */
export function resolveDocumentMedia(
    document: Entity<'product_document'> | null,
    getCachedMedia: (id: EntityKey<'media'>) => Entity<'media'> | undefined,
): Entity<'media'> | undefined {
    const mediaId = document?.mediaId || document?.translated?.mediaId;
    return mediaId
        ? getCachedMedia(mediaId) || document?.translations?.find((item) => item.mediaId === mediaId)?.media
        : undefined;
}

/** @private */
export function canMoveDocument(
    documents: EntityCollection<'product_document'>,
    document: Entity<'product_document'>,
    offset: number,
): boolean {
    const index = documents.indexOf(document);
    const newIndex = index + offset;
    return index >= 0 && newIndex >= 0 && newIndex < documents.length;
}

/** @private */
export function getDocumentMimeTypesByExtension(extensionByType: Record<string, string[]>, allowedExtensions: string[]) {
    const mimeTypes = Object.entries(extensionByType);
    return Object.fromEntries(
        allowedExtensions.map((extension) => [
            extension,
            mimeTypes.filter(([, extensions]) => extensions.includes(extension)).map(([mimeType]) => mimeType),
        ]),
    );
}

/** @private */
export function getDocumentUploadHintParams({
    allowedExtensions,
    maxFileSize,
}: {
    allowedExtensions: string[];
    maxFileSize: number;
}) {
    return {
        extensions: allowedExtensions.join(', ').toUpperCase(),
        size: Shopware.Utils.format.fileSize(maxFileSize),
    };
}

/** @private */
export function getDocumentMediaIds(documents: Entity<'product_document'>[]): string[] {
    return [
        ...new Set(
            documents
                .map((document) => document.mediaId || document.translated?.mediaId)
                .filter((id) => typeof id === 'string'),
        ),
    ];
}

/** @private */
export function createProductDocument(
    media: Entity<'media'>,
    product: Entity<'product'>,
    repository: Repository<'product_document'>,
): Entity<'product_document'> {
    const document = repository.create(Shopware.Context.api);
    document.productId = product.id;
    document.mediaId = media.id;
    document.title = media.fileName ?? '';
    document.position = (product.documents as EntityCollection<'product_document'>).length;
    return document;
}

/** @private */
export function moveDocument(
    documents: EntityCollection<'product_document'>,
    document: Entity<'product_document'>,
    offset: number,
): void {
    const oldIndex = documents.indexOf(document);
    documents.moveItem(oldIndex, oldIndex + offset);
    refreshDocumentPositions(documents);
}

/** @private */
export function removeDocuments(
    product: Entity<'product'>,
    documents: Entity<'product_document'>[],
    variants: Entity<'product'>[],
    options: {
        context: typeof Shopware.Context.api;
        getMedia: MediaResolver;
        isSystemDefaultLanguage: boolean;
        removeFromVariants: boolean;
    },
): Set<string> {
    const productDocuments = product.documents as EntityCollection<'product_document'>;
    const removedVariantIds = new Set<string>();
    documents.forEach((document) => {
        if (options.removeFromVariants && (document.mediaId || document.translated?.mediaId)) {
            stageVariantDocumentRemovals(product, variants, document, options).forEach((id) => removedVariantIds.add(id));
        }
        if (options.isSystemDefaultLanguage) {
            productDocuments.remove(document.id);
        } else {
            resetDocumentLanguageVersion(document, options.context, options.getMedia);
        }
    });
    if (options.isSystemDefaultLanguage) {
        refreshDocumentPositions(productDocuments);
    }
    return removedVariantIds;
}

/** @private */
export function getDocumentTitlePlaceholder(
    document: Entity<'product_document'>,
    context: typeof Shopware.Context.api,
    media: Entity<'media'> | undefined,
): string {
    const changes = getDocumentTranslationChanges(document, { title: null }, context);
    return (changes.translated ?? document.translated)?.title || media?.fileName || '';
}

/** @private */
export function getDocumentFileSize(media: Entity<'media'> | undefined): string {
    const size = media?.fileSize;
    return size == null ? '' : Shopware.Utils.format.fileSize(size);
}

/** @private */
export function getDocumentFileName(media: Entity<'media'> | undefined): string {
    return media?.fileName ? [media.fileName, media.fileExtension].filter(Boolean).join('.') : '';
}
