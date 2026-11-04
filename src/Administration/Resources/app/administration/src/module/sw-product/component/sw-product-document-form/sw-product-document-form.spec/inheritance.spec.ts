/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';
import Entity from 'src/core/data/entity.data';
import EntityFactory from 'src/core/data/entity-factory.data';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import { createWrapper, createMedia, clickButton, getDocuments } from './helpers';

describe('sw-product-document-form inheritance', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = repositoryFactory.create('product').create();
        store.parentProduct = repositoryFactory.create('product').create();
    });
    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
    });

    it('copies assignments before editing a variant and restores inheritance', async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.product.parentId = store.parentProduct.id;
        const parentDocument = repositoryFactory.create('product_document').create();
        const parentMedia = createMedia();
        Object.assign(parentDocument, { title: 'Parent manual', mediaId: parentMedia.id, position: 0 });
        const translation = new EntityFactory().create(
            'product_document_translation',
            null,
            Shopware.Context.api,
        ) as Entity<'product_document_translation'>;
        Object.assign(translation, {
            languageId: Shopware.Context.api.languageId,
            mediaId: parentMedia.id,
            media: parentMedia,
        });
        parentDocument.translations?.add(translation);
        store.parentProduct.documents?.add(parentDocument);
        const wrapper = await createWrapper();
        expect(wrapper.text()).toContain('Parent manual');
        expect(wrapper.get('.sw-product-document-form__add-button').attributes('disabled')).toBeDefined();

        await wrapper.get('.sw-inheritance-switch--is-inherited .mt-icon').trigger('click');
        expect(getDocuments()?.[0].id).not.toBe(parentDocument.id);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Variant manual');
        await clickButton(wrapper, 'global.default.apply');
        expect(parentDocument.title).toBe('Parent manual');
        expect(wrapper.text()).toContain('Variant manual');

        await wrapper.get('.sw-inheritance-switch--is-not-inherited .mt-icon').trigger('click');
        expect(getDocuments()).toHaveLength(0);
        expect(wrapper.text()).toContain('Parent manual');
    });

    it('copies all document translations when removing variant inheritance', async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.product.parentId = store.parentProduct.id;
        const document = repositoryFactory.create('product_document').create();
        const media = createMedia();
        const translation = new Entity('translation', 'product_document_translation', {
            languageId: repositoryFactory.create('language').create().id,
            productDocumentId: document.id,
            productDocumentVersionId: document.versionId,
            mediaId: media.id,
            media,
            title: 'Translated manual',
            createdAt: document.createdAt,
        });
        Object.assign(document, { mediaId: media.id, title: 'Translated manual' });
        document.translations.add(translation);
        store.parentProduct.documents?.add(document);
        const wrapper = await createWrapper();

        await wrapper.get('.sw-inheritance-switch--is-inherited .mt-icon').trigger('click');

        const copiedDocument = getDocuments()?.[0];
        expect(copiedDocument?.translations[0]).not.toBe(translation);
        expect(copiedDocument?.translations[0].productDocumentId).toBe(copiedDocument?.id);
        expect(translation.productDocumentId).toBe(document.id);
        expect(wrapper.text()).toContain('1.00KB');
        expect(new ChangesetGenerator().generate(store.product)).toMatchObject({
            changes: {
                documents: [
                    expect.objectContaining({
                        translations: [expect.objectContaining({ mediaId: media.id, title: 'Translated manual' })],
                    }),
                ],
            },
        });
    });

    it('detaches an empty inherited list and restores inheritance without adding assignments', async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.product.parentId = store.parentProduct.id;
        const wrapper = await createWrapper();
        expect(wrapper.get('.sw-product-document-form__empty-state button').attributes('disabled')).toBeDefined();

        await wrapper.get('.sw-inheritance-switch--is-inherited .mt-icon').trigger('click');
        expect(wrapper.get('.sw-product-document-form__empty-state button').attributes('disabled')).toBeUndefined();
        expect(getDocuments()).toHaveLength(0);
        expect(store.parentProduct.documents).toHaveLength(0);

        await wrapper.get('.sw-inheritance-switch--is-not-inherited .mt-icon').trigger('click');
        expect(wrapper.get('.sw-product-document-form__empty-state button').attributes('disabled')).toBeDefined();
        expect(getDocuments()).toHaveLength(0);
    });

    it('resets the inheritance override when navigating to another variant', async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.product.parentId = store.parentProduct.id;
        const wrapper = await createWrapper();
        await wrapper.get('.sw-inheritance-switch--is-inherited .mt-icon').trigger('click');
        expect(wrapper.get('.sw-product-document-form__empty-state button').attributes('disabled')).toBeUndefined();

        const nextVariant = repositoryFactory.create('product').create();
        nextVariant.parentId = store.parentProduct.id;
        store.product = nextVariant;
        await flushPromises();

        expect(wrapper.find('.sw-inheritance-switch--is-inherited').exists()).toBe(true);
        expect(wrapper.get('.sw-product-document-form__empty-state button').attributes('disabled')).toBeDefined();
        expect(nextVariant.documents).toHaveLength(0);
    });

    it('prevents a viewer from removing document inheritance', async () => {
        Object.assign(global, { activeAclRoles: ['product.viewer'] });
        const store = Shopware.Store.get('swProductDetail');
        store.product.parentId = store.parentProduct.id;
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-inheritance-switch').classes()).toContain('sw-inheritance-switch--disabled');
        await wrapper.get('.sw-inheritance-switch .mt-icon').trigger('click');
        expect(wrapper.find('.sw-inheritance-switch--is-inherited').exists()).toBe(true);
        expect(getDocuments()).toHaveLength(0);
    });
});
