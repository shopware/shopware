/**
 * @sw-package inventory
 */
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import type Repository from 'src/core/data/repository.data';
import type { DocumentChanges } from './document-translation.utils';
import {
    applyDocumentChanges,
    resetDocumentLanguageVersion,
    stageVariantDocumentChanges,
    stageVariantDocumentRemovals,
} from './document-changes.helper';

/** @private */
export default function useDocumentChanges() {
    const store = Shopware.Store.get('swProductDetail');
    const context = Shopware.Store.get('context');
    const { product } = storeToRefs(store);
    const { isSystemDefaultLanguage } = storeToRefs(context);
    const languageId = computed(() => context.api.languageId);
    const documentContext = computed(() => ({ ...Shopware.Context.api, language: store.documentLanguage }));

    function getMedia(document: Entity<'product_document'> | null): Entity<'media'> | undefined {
        const mediaId = document?.mediaId || document?.translated?.mediaId;
        return mediaId
            ? store.getProductDocumentMedia(mediaId) ||
                  document?.translations?.find((item) => item.mediaId === mediaId)?.media
            : undefined;
    }

    async function loadDocumentVariants(
        repository: Repository<'product'>,
        productId: string,
        mediaId: string | string[],
        apiContext: typeof Shopware.Context.api,
    ): Promise<Entity<'product'>[]> {
        const { Criteria } = Shopware.Data;
        const variants: Entity<'product'>[] = [];
        let page = 1;
        let result: EntityCollection<'product'>;
        do {
            const criteria = new Criteria(page, 100);
            criteria.setTotalCountMode(0);
            criteria.addSorting(Criteria.sort('id'));
            criteria.addFilter(Criteria.equals('parentId', productId));
            criteria.addFilter(
                Array.isArray(mediaId)
                    ? Criteria.equalsAny('documents.mediaId', mediaId)
                    : Criteria.equals('documents.mediaId', mediaId),
            );
            criteria.addAssociation('documents.translations.media');
            criteria.getAssociation('documents').addSorting(Criteria.sort('position', 'ASC'));
            result = await repository.search(criteria, apiContext);
            variants.push(...result);
            page += 1;
        } while (result.length === 100);
        return variants;
    }

    return {
        loadDocumentVariants,
        product,
        languageId,
        isSystemDefaultLanguage,
        documentContext,
        getMedia,
        applyDocumentChanges: (document: Entity<'product_document'>, changes: DocumentChanges) =>
            applyDocumentChanges(document, changes, documentContext.value, getMedia),
        resetDocumentLanguageVersion: (document: Entity<'product_document'>) =>
            resetDocumentLanguageVersion(document, documentContext.value, getMedia),
        stageVariantDocumentChanges: (
            variants: Entity<'product'>[],
            original: Entity<'product_document'>,
            changes: DocumentChanges,
        ) =>
            stageVariantDocumentChanges(product.value, variants, original, {
                changes,
                context: documentContext.value,
                getMedia,
            }),
        stageVariantDocumentRemovals: (variants: Entity<'product'>[], original: Entity<'product_document'>) =>
            stageVariantDocumentRemovals(product.value, variants, original, { context: documentContext.value, getMedia }),
    };
}
