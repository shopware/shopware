/**
 * @sw-package inventory
 */
import {
    getDocumentTranslationChanges,
    getDocumentTranslationResetChanges,
    type DocumentChanges,
} from './document-translation.utils';

type DocumentChangeContext = typeof Shopware.Context.api;
type MediaResolver = (document: Entity<'product_document'> | null) => Entity<'media'> | undefined;

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

/** @private */
export function refreshDocumentPositions(documents: EntityCollection<'product_document'>): void {
    documents.forEach((document, index) => {
        document.position = index;
    });
}

/** @private */
export function applyDocumentChanges(
    document: Entity<'product_document'>,
    changes: DocumentChanges,
    context: DocumentChangeContext,
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

/** @private */
export function resetDocumentLanguageVersion(
    document: Entity<'product_document'>,
    context: DocumentChangeContext,
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
    { changes, context, getMedia }: { changes: DocumentChanges; context: DocumentChangeContext; getMedia: MediaResolver },
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

/** @private */
export function stageVariantDocumentRemovals(
    product: Entity<'product'>,
    variants: Entity<'product'>[],
    original: Entity<'product_document'>,
    { context, getMedia }: { context: DocumentChangeContext; getMedia: MediaResolver },
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
