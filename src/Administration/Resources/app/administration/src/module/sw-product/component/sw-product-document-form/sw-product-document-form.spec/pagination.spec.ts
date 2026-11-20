/**
 * @sw-package inventory
 */
import type { ComponentPublicInstance } from 'vue';
import { createProduct, cleanupWrappers, createWrapper, createMedia, addDocuments, getDocuments } from './helpers';

describe('sw-product-document-form pagination', () => {
    const originalLanguageId = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguageId;
        cleanupWrappers();
    });

    it('shows ten rows per page and moves documents across page boundaries', async () => {
        const wrapper = await createWrapper();
        await addDocuments(
            wrapper,
            Array.from({ length: 12 }, (_, index) => createMedia(`manual-${index}`)),
        );

        expect(wrapper.findAll('.document-row')).toHaveLength(10);
        expect(wrapper.findAll('.document-number').map((row) => row.text())).toEqual(
            Array.from({ length: 10 }, (_, index) => String(index + 1)),
        );
        const lastRow = wrapper.findAll('.document-row')[9];
        await lastRow.findAll('.sw-product-document-form__position button')[0].trigger('click');
        await wrapper.get('.sw-pagination__page-button-next').trigger('click');

        expect(wrapper.findAll('.document-row')).toHaveLength(2);
        expect(wrapper.findAll('.document-number').map((row) => row.text())).toEqual(['11', '12']);
        expect(wrapper.findAll('.document-row')[0].text()).toContain('manual-9');
        const moveUp = wrapper.findAll('.document-row')[0].findAll('.sw-product-document-form__position button')[1];
        expect(moveUp.attributes('disabled')).toBeUndefined();
        await moveUp.trigger('click');
        expect(getDocuments()![9].title).toBe('manual-9');
        expect(wrapper.findAll('.document-row')[0].text()).toContain('manual-10');
        expect(
            wrapper
                .findAll('.document-row')[1]
                .findAll('.sw-product-document-form__position button')[0]
                .attributes('disabled'),
        ).toBeDefined();

        wrapper
            .getComponent<ComponentPublicInstance>('.sw-pagination__per-page .mt-select')
            .vm.$emit('update:modelValue', '25');
        await flushPromises();
        expect(wrapper.findAll('.document-row')).toHaveLength(12);
        expect(wrapper.findAll('.document-number')[0].text()).toBe('1');
        expect(wrapper.get('.sw-pagination__page-button-next').attributes('disabled')).toBeDefined();
    });

    it('removes selections from multiple pages and returns to an existing page', async () => {
        const wrapper = await createWrapper();
        await addDocuments(
            wrapper,
            Array.from({ length: 11 }, (_, index) => createMedia(`manual-${index}`)),
        );
        const first = getDocuments()![0];
        const last = getDocuments()![10];
        const grid = wrapper.getComponent<ComponentPublicInstance>({ ref: 'documentGrid' });
        await grid.setData({ hasSelection: true });
        grid.vm.$emit('select-item', { [first.id]: first });
        await wrapper.get('.sw-pagination__page-button-next').trigger('click');
        grid.vm.$emit('select-item', { [first.id]: first, [last.id]: last });
        await flushPromises();
        await wrapper.get('.mt-link--critical').trigger('click');
        await wrapper.get('.sw-product-document-form__confirm-remove').trigger('click');
        await flushPromises();

        expect(getDocuments()).toHaveLength(9);
        expect(getDocuments()!.has(first.id)).toBe(false);
        expect(getDocuments()!.has(last.id)).toBe(false);
        expect(wrapper.findAll('.document-row')).toHaveLength(9);
        expect(wrapper.find('.sw-pagination').exists()).toBe(false);
        expect(wrapper.findAll('.document-number')[0].text()).toBe('1');
    });

    it('paginates inherited documents using the parent collection', async () => {
        const parent = await createWrapper();
        await addDocuments(
            parent,
            Array.from({ length: 12 }, (_, index) => createMedia(`parent-${index}`)),
        );
        const store = Shopware.Store.get('swProductDetail');
        store.parentProduct = store.product;
        store.product = createProduct();
        const wrapper = await createWrapper(undefined, undefined, undefined, { isInherited: true });

        expect(wrapper.findAll('.document-row')).toHaveLength(10);
        await wrapper.get('.sw-pagination__page-button-next').trigger('click');
        expect(wrapper.findAll('.document-row')).toHaveLength(2);
        expect(wrapper.findAll('.document-row')[0].text()).toContain('parent-10');
        expect(getDocuments()).toHaveLength(0);
    });
});
