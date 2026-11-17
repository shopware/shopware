/**
 * @sw-package inventory
 */
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import {
    getDocumentTranslationChanges,
    getDocumentTranslationResetChanges,
    type DocumentChanges,
} from './document-translation.utils';
import { getDocumentVariantCandidates } from './document-variant.helper';
import { updateDocumentPositions } from './document-position.helper';

function getMedia(document: Entity<'product_document'> | null): Entity<'media'> | undefined {
    const mediaId = document?.mediaId || document?.translated?.mediaId;

    return mediaId
        ? Shopware.Store.get('swProductDetail').getDocumentMedia(mediaId) ||
              document?.translations?.find((item) => item.mediaId === mediaId)?.media
        : undefined;
}

function applyDocumentChanges(document: Entity<'product_document'>, changes: DocumentChanges): void {
    Object.assign(document, changes);
    const translation = document.translations?.find((entry) => entry.languageId === Shopware.Context.api.languageId);
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

function resetDocumentLanguageVersion(document: Entity<'product_document'>): void {
    const { systemLanguageId } = Shopware.Context.api;
    const language = Shopware.Store.get('swProductDetail').documentLanguage;
    applyDocumentChanges(document, getDocumentTranslationResetChanges(document, language, systemLanguageId));
}

/** @private */
export default function useDocumentChanges() {
    const store = Shopware.Store.get('swProductDetail');
    const { product } = storeToRefs(store);
    const languageId = computed(() => Shopware.Store.get('context').api.languageId);
    const documentContext = computed(() => ({ ...Shopware.Context.api, language: store.documentLanguage }));
    const systemLanguageId = computed(() => Shopware.Store.get('context').api.systemLanguageId);
    const isDefaultLanguage = computed(() => languageId.value === systemLanguageId.value);

    function stageVariantDocumentChanges(
        variants: Entity<'product'>[],
        original: Entity<'product_document'>,
        changes: DocumentChanges,
    ): string[] {
        const children =
            product.value.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
        const candidates = getDocumentVariantCandidates(variants, children, original);
        const updatedVariantIds: string[] = [];
        const context = documentContext.value;
        candidates.forEach(({ variant, matching }) => {
            let hasChanges = false;
            matching.forEach((copy) => {
                const update = getDocumentTranslationChanges(copy, changes, context, isDefaultLanguage.value);
                const { title, mediaId } = copy;
                applyDocumentChanges(copy, update);
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
        product.value.children = children;
        return updatedVariantIds;
    }

    function stageVariantDocumentRemovals(variants: Entity<'product'>[], original: Entity<'product_document'>): string[] {
        const children =
            product.value.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
        const origin = product.value.getOrigin();
        const originChildren =
            origin.children || new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
        const candidates = getDocumentVariantCandidates(variants, children, original);
        candidates.forEach(({ variant, documents, matching }) => {
            // Register loaded children in the origin so the changeset includes nested document deletions.
            if (!variant.isNew() && !originChildren.has(variant.id)) {
                originChildren.add(variant);
            }
            if (!children.has(variant.id)) {
                children.add(variant);
            }
            if (isDefaultLanguage.value) {
                matching.forEach((copy) => documents.remove(copy.id));
                updateDocumentPositions(documents);
            } else {
                matching.forEach((copy) => resetDocumentLanguageVersion(copy));
            }
        });
        product.value.children = children;
        origin.children = originChildren;
        return candidates.map(({ variant }) => variant.id);
    }

    return {
        product,
        languageId,
        systemLanguageId,
        isDefaultLanguage,
        documentContext,
        getMedia,
        applyDocumentChanges,
        resetDocumentLanguageVersion,
        stageVariantDocumentChanges,
        stageVariantDocumentRemovals,
    };
}
