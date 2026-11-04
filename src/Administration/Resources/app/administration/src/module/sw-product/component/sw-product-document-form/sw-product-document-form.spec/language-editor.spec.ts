/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import EntityFactory from 'src/core/data/entity-factory.data';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import { createWrapper, createMedia, clickButton, selectMedia } from './helpers';

describe('sw-product-document-form language editor', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    const originalLanguage = Shopware.Store.get('context').api.language;
    let wrapper: VueWrapper;
    let document: Entity<'product_document'>;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        Object.assign(Shopware.Store.get('context').api, { language: { name: 'Deutsch', locale: { code: 'de-DE' } } });
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = repositoryFactory.create('product').create();
        store.parentProduct = repositoryFactory.create('product').create();
        document = repositoryFactory.create('product_document').create();
        const media = createMedia('english-manual');
        Object.assign(document, {
            productId: store.product.id,
            title: null,
            mediaId: null,
            position: 4,
            translated: { title: 'Manual', mediaId: media.id },
        });
        const translation = new EntityFactory().create(
            'product_document_translation',
            null,
            Shopware.Context.api,
        ) as Entity<'product_document_translation'>;
        Object.assign(translation, {
            languageId: Shopware.Context.api.systemLanguageId,
            title: 'Manual',
            mediaId: media.id,
            media,
        });
        document.translations.add(translation);
        store.product.documents!.add(document);
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
        Shopware.Context.api.languageId = originalLanguageId;
        Shopware.Store.get('context').api.language = originalLanguage;
    });

    it('offers Add language version and opens an optional name/file editor showing the fallback explanation', async () => {
        wrapper = await createWrapper();
        const menu = wrapper.findAll('sw-context-menu-item-stub');
        expect(menu).toHaveLength(2);
        expect(menu[0].text()).toBe('sw-product.documentForm.addLanguageVersion');
        await menu[0].trigger('click');
        const input = wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input');
        expect(input.element.value).toBe('');
        expect(input.attributes('placeholder')).toBe('Manual');
        expect(input.element.required).toBe(false);
        expect(wrapper.find('.sw-product-document-form__selected-file').exists()).toBe(false);
        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBe(true);
        expect(wrapper.text()).toContain('sw-product.documentForm.fileFallbackHint');
        await clickButton(wrapper, 'global.default.apply');
        expect(document).toMatchObject({ title: null, mediaId: null, position: 4 });
        expect(wrapper.find('.mt-badge').exists()).toBe(false);
    });

    it('keeps a typed name as an explicit override even when it equals the fallback name', async () => {
        wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__name-input input').setValue('Manual');
        await clickButton(wrapper, 'global.default.apply');
        expect(document).toMatchObject({ title: 'Manual', mediaId: null, position: 4 });
        expect(wrapper.findAll('.mt-badge')).toHaveLength(1);
        expect(wrapper.findAll('sw-context-menu-item-stub')[0].text()).toBe('global.default.edit');
        expect(wrapper.findAll('sw-context-menu-item-stub').at(-1)!.text()).toBe(
            'sw-product.documentForm.removeLanguageVersion',
        );
    });

    it('adds a file-only version and restores file fallback when that file is cleared', async () => {
        wrapper = await createWrapper();
        const media = createMedia('german-manual');
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await selectMedia(wrapper, [media]);
        await clickButton(wrapper, 'global.default.apply');
        expect(document).toMatchObject({ title: null, mediaId: media.id, position: 4 });
        expect(wrapper.find('.document-row').text()).toContain('german-manual.pdf');
        expect(wrapper.findAll('.mt-badge')).toHaveLength(1);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
        await clickButton(wrapper, 'global.default.apply');
        expect(document).toMatchObject({ title: null, mediaId: null, position: 4 });
        expect(wrapper.find('.document-row').text()).toContain('english-manual.pdf');
        expect(wrapper.find('.mt-badge').exists()).toBe(false);
    });

    it('clears the German name without clearing its German file', async () => {
        wrapper = await createWrapper();
        const media = createMedia('german-manual');
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__name-input input').setValue('Handbuch');
        await selectMedia(wrapper, [media]);
        await clickButton(wrapper, 'global.default.apply');
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__name-input input').setValue('');
        await clickButton(wrapper, 'global.default.apply');
        expect(document).toMatchObject({ title: null, mediaId: media.id });
        expect(wrapper.find('.document-row').text()).toContain('Manual');
        expect(wrapper.find('.document-row').text()).toContain('german-manual.pdf');
        expect(wrapper.findAll('.mt-badge')).toHaveLength(1);
    });

    it('preserves a variant name override when applying only a new file', async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.product.childCount = 1;
        const variant = repositoryFactory.create('product').create();
        variant.parentId = store.product.id;
        const copy = repositoryFactory.create('product_document').create();
        Object.assign(copy, {
            productId: variant.id,
            title: 'Variant name',
            mediaId: null,
            translated: document.translated,
        });
        Object.assign(copy.getOrigin(), { title: 'Variant name', mediaId: null });
        variant.documents!.add(copy);
        const repository = repositoryFactory.create('product');
        jest.spyOn(repository, 'search').mockResolvedValue(
            new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [variant]),
        );
        wrapper = await createWrapper(undefined, undefined, repository);
        const media = createMedia('new-german-file');
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await selectMedia(wrapper, [media]);
        await wrapper.get('.sw-product-document-form__apply-to-variants input').setValue(true);
        await clickButton(wrapper, 'global.default.apply');
        await flushPromises();
        expect(copy).toMatchObject({ title: 'Variant name', mediaId: media.id });
        expect(document).toMatchObject({ title: null, mediaId: media.id });
    });

    it('discards a selected file and entered name on Cancel', async () => {
        wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__name-input input').setValue('Discard this name');
        await selectMedia(wrapper, [createMedia('discard-file')]);
        await clickButton(wrapper, 'global.default.cancel');
        expect(document).toMatchObject({ title: null, mediaId: null, position: 4 });
        expect(wrapper.find('.document-row').text()).toContain('english-manual.pdf');
        expect(wrapper.find('.mt-badge').exists()).toBe(false);
    });
});
