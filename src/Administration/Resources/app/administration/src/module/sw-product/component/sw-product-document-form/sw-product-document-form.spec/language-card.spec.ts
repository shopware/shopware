/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import { createWrapper, createMedia, getDocuments } from './helpers';

describe('sw-product-document-form language markers', () => {
    let wrapper: VueWrapper;
    const originalLanguage = Shopware.Context.api.languageId;
    const originalLanguageEntity = Shopware.Store.get('context').api.language;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        Object.assign(Shopware.Store.get('context').api, {
            language: { id: Shopware.Context.api.languageId, name: 'Deutsch', locale: { code: 'de-DE' } },
        });
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = repositoryFactory.create('product').create();
        store.parentProduct = repositoryFactory.create('product').create();
    });

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Context.api.languageId = originalLanguage;
        Shopware.Store.get('context').api.language = originalLanguageEntity;
    });

    it.each([
        [false, false],
        [true, false],
        [false, true],
        [true, true],
    ])('marks name and file overrides independently (name: %s, file: %s)', async (nameTranslated, fileTranslated) => {
        const document = repositoryFactory.create('product_document').create();
        const defaultMedia = createMedia('english-file');
        const translatedMedia = createMedia('german-file');
        Object.assign(document, {
            title: nameTranslated ? 'Deutscher Name' : null,
            mediaId: fileTranslated ? translatedMedia.id : null,
            translated: {
                title: nameTranslated ? 'Deutscher Name' : 'English name',
                mediaId: fileTranslated ? translatedMedia.id : defaultMedia.id,
            },
            translations: [
                { mediaId: defaultMedia.id, media: defaultMedia },
                { mediaId: translatedMedia.id, media: translatedMedia },
            ],
        });
        getDocuments()?.add(document);
        wrapper = await createWrapper();
        const values = wrapper.findAll('.sw-product-document-language-value');
        expect(values[0].text()).toContain(nameTranslated ? 'Deutscher Name' : 'English name');
        expect(values[1].text()).toContain(fileTranslated ? 'german-file.pdf' : 'english-file.pdf');
        expect(values[0].find('.mt-badge').exists()).toBe(nameTranslated);
        expect(values[1].find('.mt-badge').exists()).toBe(fileTranslated);
        expect(values[0].classes('sw-product-document-language-value--fallback')).toBe(!nameTranslated);
        expect(values[1].classes('sw-product-document-language-value--fallback')).toBe(!fileTranslated);
        expect(wrapper.find('.sw-product-document-form__language-hint').exists()).toBe(false);
        expect(wrapper.get<HTMLButtonElement>('.sw-product-document-form__add-button button').element.disabled).toBe(true);
        expect(Array.from(wrapper.findAll('.mt-badge'), (badge) => badge.text())).toEqual(
            Array(Number(nameTranslated) + Number(fileTranslated)).fill('DE'),
        );
    });

    it('shows default-language values without translation badges or fallback styling', async () => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const document = repositoryFactory.create('product_document').create();
        const media = createMedia();
        Object.assign(document, { title: 'Manual', mediaId: media.id, translations: [{ mediaId: media.id, media }] });
        getDocuments()?.add(document);
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-product-document-language-value .mt-badge').exists()).toBe(false);
        expect(wrapper.find('.sw-product-document-language-value--fallback').exists()).toBe(false);
        expect(wrapper.get<HTMLButtonElement>('.sw-product-document-form__add-button button').element.disabled).toBe(false);
    });
});
