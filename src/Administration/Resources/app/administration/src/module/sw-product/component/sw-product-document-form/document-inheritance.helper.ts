/**
 * @sw-package inventory
 */
import type EntityFactory from 'src/core/data/entity-factory.data';
import type Repository from 'src/core/data/repository.data';

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
