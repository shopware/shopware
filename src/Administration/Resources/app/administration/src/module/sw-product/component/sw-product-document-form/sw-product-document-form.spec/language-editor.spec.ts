/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import EntityFactory from 'src/core/data/entity-factory.data';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import { createProduct, cleanupWrappers, createWrapper, createMedia, clickButton, selectMedia } from './helpers';

describe('sw-product-document-form language editor', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    const originalLanguage = Shopware.Store.get('context').api.language;
    let wrapper: VueWrapper;
    let document: Entity<'product_document'>;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        Object.assign(Shopware.Store.get('context').api, {
            language: { id: Shopware.Context.api.languageId, name: 'Deutsch', locale: { code: 'de-DE' } },
        });
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
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
        cleanupWrappers();
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
        expect(wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').props()).toMatchObject({ source: null });
        expect(wrapper.find('.sw-media-upload-v2').exists()).toBe(true);
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
        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
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
        const input = wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input');
        expect(input.element.value).toBe('Handbuch');
        await input.setValue('');
        expect(input.attributes('placeholder')).toBe('Manual');
        await clickButton(wrapper, 'global.default.apply');
        expect(document).toMatchObject({ title: null, mediaId: media.id });
        expect(wrapper.find('.document-row').text()).toContain('Manual');
        expect(wrapper.find('.document-row').text()).toContain('german-manual.pdf');
        expect(wrapper.findAll('.mt-badge')).toHaveLength(1);
    });

    it('previews the parent-language name that will be applied when clearing an override', async () => {
        const parentLanguageId = repositoryFactory.create('language').create().id;
        Object.assign(Shopware.Store.get('context').api.language!, { parentId: parentLanguageId });
        const parentTranslation = new EntityFactory().create(
            'product_document_translation',
            null,
            Shopware.Context.api,
        ) as Entity<'product_document_translation'>;
        Object.assign(parentTranslation, { languageId: parentLanguageId, title: 'Parent-language name', mediaId: null });
        document.translations.add(parentTranslation);
        Object.assign(document, {
            title: 'Current override',
            translated: { ...document.translated, title: 'Current override' },
        });
        wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        const input = wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input');
        expect(input.element.value).toBe('Current override');
        await input.setValue('');
        expect(input.attributes('placeholder')).toBe('Parent-language name');
        await clickButton(wrapper, 'global.default.apply');
        expect(wrapper.find('.document-row').text()).toContain('Parent-language name');
        expect(wrapper.find('.document-row').text()).toContain('english-manual.pdf');
    });

    it.each(['name', 'file', 'both'])(
        'keeps the cleared %s out of the save payload after unlinking inheritance',
        async (field) => {
            const store = Shopware.Store.get('swProductDetail');
            const germanMedia = createMedia('german-manual');
            const englishMediaId = document.translations[0].mediaId;
            Object.assign(document, {
                productId: store.parentProduct.id,
                title: 'Handbuch',
                mediaId: germanMedia.id,
                translated: { title: 'Handbuch', mediaId: germanMedia.id },
            });
            const translation = new EntityFactory().create(
                'product_document_translation',
                null,
                Shopware.Context.api,
            ) as Entity<'product_document_translation'>;
            Object.assign(translation, {
                languageId: Shopware.Context.api.languageId,
                title: 'Handbuch',
                mediaId: germanMedia.id,
                media: germanMedia,
            });
            document.translations.add(translation);
            store.product.documents!.remove(document.id);
            store.parentProduct.documents!.add(document);
            store.product.parentId = store.parentProduct.id;
            wrapper = await createWrapper();

            await wrapper.get('.sw-inheritance-switch--is-inherited .mt-icon').trigger('click');
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            if (field !== 'file') {
                await wrapper.get('.sw-product-document-form__name-input input').setValue('');
            }
            if (field !== 'name') {
                await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            }
            await clickButton(wrapper, 'global.default.apply');
            const copy = store.product.documents![0];
            expect(copy).toMatchObject({
                title: field === 'file' ? 'Handbuch' : null,
                mediaId: field === 'name' ? germanMedia.id : null,
            });

            const repository = repositoryFactory.create('product');
            const postSpy = jest.spyOn(repository.httpClient, 'post').mockResolvedValue({ data: {} });
            await repository.save(store.product, Shopware.Context.api);
            const payload = JSON.stringify(postSpy.mock.calls[0][1]);
            expect(payload.includes('Handbuch')).toBe(field === 'file');
            expect(payload.includes(germanMedia.id)).toBe(field === 'name');
            expect(payload).toContain('Manual');
            expect(payload).toContain(englishMediaId);
            expect(document).toMatchObject({ title: 'Handbuch', mediaId: germanMedia.id });
        },
    );

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
