/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

import { createWrapper, createMedia, addMedia, getDocuments } from './helpers';

describe('sw-product-document-form ordering', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        Object.assign(store, { product: repositoryFactory.create('product').create() });
        store.parentProduct = repositoryFactory.create('product').create();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
    });

    it('reorders documents and persists their new positions', async () => {
        const wrapper = await createWrapper();
        await addMedia(wrapper, [createMedia('first'), createMedia('second')]);
        await wrapper.findAll('.document-row')[1].findAll('button')[1].trigger('click');

        expect(Array.from(getDocuments() ?? [], (document) => document.title)).toEqual(['second', 'first']);
        expect(Array.from(getDocuments() ?? [], (document) => document.position)).toEqual([0, 1]);
        expect(wrapper.findAll('.document-row')[0].text()).toContain('second');
        expect(wrapper.findAll('.document-row')[0].findAll('button')[1].attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('.document-row')[1].findAll('button')[0].attributes('disabled')).toBeDefined();
        await wrapper.findAll('.document-row')[0].findAll('button')[0].trigger('click');
        expect(Array.from(getDocuments() ?? [], (document) => document.title)).toEqual(['first', 'second']);
        expect(Array.from(getDocuments() ?? [], (document) => document.position)).toEqual([0, 1]);
    });
});
