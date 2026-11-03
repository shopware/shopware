/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import { createWrapper, createMedia, clickButton, getDocuments } from './helpers';

describe('sw-product-document-form removal', () => {
    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = repositoryFactory.create('product').create();
        store.parentProduct = repositoryFactory.create('product').create();
    });

    it('cancels removal without changing the product draft', async () => {
        const document = repositoryFactory.create('product_document').create();
        document.title = 'Manual';
        getDocuments()?.add(document);
        const wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        expect(wrapper.text()).toContain('sw-product.documentForm.removeDescription');
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()).toHaveLength(1);
    });

    it('stages the persisted document deletion and keeps its media file', async () => {
        const media = createMedia();
        const product = Shopware.Store.get('swProductDetail').product;
        const document = repositoryFactory.create('product_document').create();
        Object.assign(document, { title: 'Manual', mediaId: media.id, productId: product.id });
        Object.assign(document.getOrigin(), { title: 'Manual', mediaId: media.id, productId: product.id });
        document._isNew = false;
        getDocuments()?.add(document);
        product.getOrigin().documents = Shopware.Data.EntityCollection.fromCollection(getDocuments()!);
        product._isNew = false;
        const wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();
        const payload = new ChangesetGenerator().generate(product);
        expect(getDocuments()).toHaveLength(0);
        expect(payload.deletionQueue).toEqual([expect.objectContaining({ entity: 'product_document', key: document.id })]);
        expect(media.fileName).toBe('manual');
    });
});
