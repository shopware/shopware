/**
 * @sw-package inventory
 */
import EntityFactory from 'src/core/data/entity-factory.data';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import { resetDocumentTranslation } from './document-translation.utils';

describe('document translation reset', () => {
    it('previews parent-language fallback per field and preserves unrelated translation data', () => {
        const document = repositoryFactory.create('product_document').create();
        const language = repositoryFactory.create('language').create();
        language.parentId = repositoryFactory.create('language').create().id;
        const parent = new EntityFactory().create(
            'product_document_translation',
            null,
            Shopware.Context.api,
        ) as Entity<'product_document_translation'>;
        Object.assign(parent, {
            languageId: language.parentId,
            title: 'Parent name',
            mediaId: null,
        });
        const system = new EntityFactory().create(
            'product_document_translation',
            null,
            Shopware.Context.api,
        ) as Entity<'product_document_translation'>;
        Object.assign(system, {
            languageId: Shopware.Context.api.systemLanguageId,
            title: 'System name',
            mediaId: 'system-file',
        });
        document.translations.add(parent);
        document.translations.add(system);
        Object.assign(document, {
            title: 'Current name',
            mediaId: 'current-file',
            translated: { customFields: { note: 'Keep' } },
        });
        expect(resetDocumentTranslation(document, language)).toEqual({
            title: null,
            mediaId: null,
            translated: { title: 'Parent name', mediaId: 'system-file', customFields: { note: 'Keep' } },
        });
        expect(document.title).toBe('Current name');
        expect(document.mediaId).toBe('current-file');
        expect(parent.title).toBe('Parent name');
        expect(system.mediaId).toBe('system-file');
    });
});
