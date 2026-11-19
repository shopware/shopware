/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import EntityFactory from 'src/core/data/entity-factory.data';
import Repository from 'src/core/data/repository.data';
import type { ComponentPublicInstance } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import { createProduct, cleanupWrappers, createWrapper, createMedia, clickButton } from './helpers';

describe('sw-product-document-form delayed language metadata', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    const originalSystemLanguageId = Shopware.Context.api.systemLanguageId;
    const originalLanguage = Shopware.Store.get('context').api.language;
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Context.api.languageId = originalLanguageId;
        Shopware.Store.get('context').api.language = originalLanguage;
        cleanupWrappers();
        jest.restoreAllMocks();
    });

    it('loads the default language once for both hints', async () => {
        Object.assign(global, { activeAclRoles: ['product.editor', 'media.viewer'] });
        const languageRepository = repositoryFactory.create('language');
        const language = languageRepository.create();
        language.name = 'English';
        const getLanguage = jest.spyOn(Repository.prototype, 'get').mockResolvedValue(language);
        Shopware.Context.api.languageId = languageRepository.create().id;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
        wrapper = await createWrapper();
        await flushPromises();
        expect(wrapper.get('.sw-product-document-empty-state__language-hint .mt-link').text()).toBe("'English'");

        const document = repositoryFactory.create('product_document').create();
        Object.assign(document, { title: 'Manual', mediaId: createMedia().id, position: 0 });
        store.product.documents!.add(document);
        await flushPromises();
        expect(wrapper.getComponent<ComponentPublicInstance>('.sw-product-document-add-button').props()).toMatchObject({
            defaultLanguageName: 'English',
        });
        expect(getLanguage).toHaveBeenCalledTimes(1);
        expect(getLanguage).toHaveBeenCalledWith(originalSystemLanguageId);
    });

    it.each(['remove version', 'clear name', 'clear file'])(
        'waits for language metadata before "%s" and uses the parent-language fallback',
        async (operation) => {
            Object.assign(global, { activeAclRoles: ['product.editor', 'media.viewer'] });
            const language = repositoryFactory.create('language').create();
            language.parentId = repositoryFactory.create('language').create().id;
            Object.assign(language, { locale: { code: 'fr-FR' } });
            const staleLanguage = repositoryFactory.create('language').create();
            Shopware.Context.api.languageId = language.id;
            Shopware.Store.get('context').api.language = staleLanguage;
            const store = Shopware.Store.get('swProductDetail');
            store.$reset();
            store.product = createProduct();
            store.parentProduct = createProduct();
            const document = repositoryFactory.create('product_document').create();
            const activeMedia = createMedia('localized-file');
            const parentMedia = createMedia('parent-language-file');
            const systemMedia = createMedia('system-language-file');
            Object.assign(document, {
                title: 'Localized title',
                mediaId: activeMedia.id,
                translated: { title: 'Localized title', mediaId: activeMedia.id },
            });
            [
                { languageId: language.id, title: 'Localized title', mediaId: activeMedia.id, media: activeMedia },
                {
                    languageId: language.parentId,
                    title: 'Parent-language title',
                    mediaId: parentMedia.id,
                    media: parentMedia,
                },
                {
                    languageId: Shopware.Context.api.systemLanguageId,
                    title: 'System title',
                    mediaId: systemMedia.id,
                    media: systemMedia,
                },
            ].forEach((values) => {
                const translation = new EntityFactory().create(
                    'product_document_translation',
                    null,
                    Shopware.Context.api,
                ) as Entity<'product_document_translation'>;
                Object.assign(translation, values);
                document.translations.add(translation);
            });
            store.product.documents!.add(document);
            let resolveLanguage!: (value: Entity<'language'>) => void;
            const loading = new Promise<Entity<'language'>>((resolve) => {
                resolveLanguage = resolve;
            }).then((loaded) => {
                store.documentLanguage = loaded;
            });
            wrapper = await createWrapper();
            const menuIndex = operation === 'remove version' ? -1 : 0;
            const action = wrapper.findAll('sw-context-menu-item-stub').at(menuIndex)!;
            expect(action.attributes('disabled')).toBe('true');
            await action.trigger('click');
            expect(wrapper.find('.upload-modal').exists()).toBe(false);
            expect(document).toMatchObject({ title: 'Localized title', mediaId: activeMedia.id });

            resolveLanguage(language);
            await loading;
            await flushPromises();
            expect(action.attributes('disabled')).toBe('false');
            // The global fetch can finish later, without locale data or for an earlier language.
            Shopware.Store.get('context').api.language = staleLanguage;
            await flushPromises();
            expect(action.attributes('disabled')).toBe('false');
            expect(wrapper.get('.sw-product-document-language-value .mt-badge').text()).toBe('FR');
            await action.trigger('click');
            if (operation === 'clear name') {
                await wrapper.get('.sw-product-document-form__name-input input').setValue('');
            } else if (operation === 'clear file') {
                await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            }
            await clickButton(wrapper, operation === 'remove version' ? 'global.default.remove' : 'global.default.apply');
            const expectedTitle = operation === 'clear file' ? 'Localized title' : 'Parent-language title';
            const expectedMedia = operation === 'clear name' ? activeMedia : parentMedia;
            expect(wrapper.get('.document-row').text()).toContain(expectedTitle);
            expect(wrapper.get('.document-row').text()).toContain(`${expectedMedia.fileName}.pdf`);
            expect(document.translated).toMatchObject({ title: expectedTitle, mediaId: expectedMedia.id });
        },
    );
});
