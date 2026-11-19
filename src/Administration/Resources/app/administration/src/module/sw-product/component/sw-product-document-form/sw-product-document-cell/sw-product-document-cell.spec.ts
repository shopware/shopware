/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import { setDocumentMedia } from '../product-document.helper';

const SYSTEM_LANGUAGE_ID = 'system-language' as EntityKey<'language'>;
const GERMAN_LANGUAGE_ID = 'german' as EntityKey<'language'>;

function createDocument(values: Record<string, unknown> = {}): Entity<'product_document'> {
    return {
        id: 'document-id',
        title: 'Manual',
        mediaId: 'media-id',
        translated: { title: 'Manual', mediaId: 'media-id' },
        translations: [],
        ...values,
    } as unknown as Entity<'product_document'>;
}

async function createWrapper(props: Record<string, unknown>) {
    return mount(await wrapTestComponent('sw-product-document-cell', { sync: true }), {
        props,
        global: {
            stubs: {
                'mt-badge': { template: '<span class="mt-badge"><slot /></span>' },
            },
            directives: {
                tooltip: {},
            },
        },
    });
}

describe('src/module/sw-product/component/sw-product-document-form/sw-product-document-cell', () => {
    const context = Shopware.Store.get('context');
    const productDetail = Shopware.Store.get('swProductDetail');
    const { languageId, systemLanguageId, language } = context.api;

    beforeEach(() => {
        context.api.languageId = SYSTEM_LANGUAGE_ID;
        context.api.systemLanguageId = SYSTEM_LANGUAGE_ID;
        productDetail.product = { id: 'product-id', parentId: null } as unknown as typeof productDetail.product;
        productDetail.parentProduct = { documents: [] } as unknown as Entity<'product'>;
    });

    afterEach(() => {
        context.api.languageId = languageId;
        context.api.systemLanguageId = systemLanguageId;
        context.api.language = language;
    });

    it('shows the translated title in the title column', async () => {
        const wrapper = await createWrapper({
            document: createDocument({ translated: { title: 'Handbuch', mediaId: 'media-id' } }),
            field: 'title',
        });

        expect(wrapper.text()).toContain('Handbuch');
    });

    it('shows the file name with its extension in the file column', async () => {
        const document = createDocument();
        setDocumentMedia(document, { id: 'media-id', fileName: 'manual', fileExtension: 'pdf' } as Entity<'media'>);

        const wrapper = await createWrapper({ document, field: 'mediaId' });

        expect(wrapper.text()).toContain('manual.pdf');
    });

    it('marks a value from the fallback language in another language', async () => {
        context.api.languageId = GERMAN_LANGUAGE_ID;

        const wrapper = await createWrapper({ document: createDocument({ title: null }), field: 'title' });

        expect(wrapper.classes()).toContain('sw-product-document-cell--fallback');
        expect(wrapper.find('.mt-badge').exists()).toBe(false);
    });

    it('shows the language code for a value of the current language', async () => {
        context.api.languageId = GERMAN_LANGUAGE_ID;
        context.api.language = {
            id: GERMAN_LANGUAGE_ID,
            name: 'Deutsch',
            locale: { code: 'de-DE' },
        } as typeof context.api.language;

        const wrapper = await createWrapper({ document: createDocument(), field: 'title' });

        expect(wrapper.classes()).not.toContain('sw-product-document-cell--fallback');
        expect(wrapper.find('.mt-badge').text()).toBe('DE');
    });

    it('shows the variant only badge for a file the parent does not have', async () => {
        productDetail.product = { id: 'variant-id', parentId: 'product-id' } as unknown as typeof productDetail.product;

        const wrapper = await createWrapper({ document: createDocument(), field: 'title', showVariantBadge: true });

        expect(wrapper.find('.sw-product-document-cell__variant-badge').text()).toBe('sw-product.documentForm.variantOnly');
    });

    it('hides the variant only badge when the parent has the file', async () => {
        productDetail.product = { id: 'variant-id', parentId: 'product-id' } as unknown as typeof productDetail.product;
        productDetail.parentProduct = {
            documents: [createDocument()],
        } as unknown as Entity<'product'>;

        const wrapper = await createWrapper({ document: createDocument(), field: 'title', showVariantBadge: true });

        expect(wrapper.find('.sw-product-document-cell__variant-badge').exists()).toBe(false);
    });
});
