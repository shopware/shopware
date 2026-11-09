/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';
import { createProduct, cleanupWrappers, createWrapper, createMedia, addMedia, clickButton, getDocuments } from './helpers';

describe('sw-product-document-form dialog sessions', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
        store.product.childCount = 1;
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
    });

    it.each([false, true])('ignores a media lookup after leaving the tab (reject: %s)', async (reject) => {
        const repository = repositoryFactory.create('media');
        const media = createMedia();
        let settle!: () => void;
        jest.spyOn(repository, 'get').mockImplementationOnce(
            () =>
                new Promise((resolve, rejectRequest) => {
                    settle = () => (reject ? rejectRequest(new Error('Late upload')) : resolve(media));
                }),
        );
        const wrapper = await createWrapper(repository);
        const notification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: media.id });
        await flushPromises();
        wrapper.unmount();
        settle();
        await flushPromises();
        expect(getDocuments()).toHaveLength(0);
        expect(notification).not.toHaveBeenCalled();
    });

    it('keeps a pending add upload when an unrelated editor is opened and cancelled', async () => {
        const repository = repositoryFactory.create('media');
        const media = createMedia('new-file');
        let resolveMedia!: (value: Entity<'media'>) => void;
        jest.spyOn(repository, 'get').mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveMedia = resolve;
                }),
        );
        const wrapper = await createWrapper(repository);
        await addMedia(wrapper, [createMedia()]);
        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: media.id });
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await clickButton(wrapper, 'global.default.cancel');
        resolveMedia(media);
        await flushPromises();
        expect(Array.from(getDocuments()!, (document) => document.title)).toEqual(['manual', 'new-file']);
        wrapper.unmount();
    });

    describe.each(['edit', 'remove'])('%s dialog', (action) => {
        it('discards a pending variant lookup after leaving the tab', async () => {
            const repository = repositoryFactory.create('product');
            let resolveVariants!: (value: EntityCollection<'product'>) => void;
            jest.spyOn(repository, 'search').mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveVariants = resolve;
                    }),
            );
            const wrapper = await createWrapper(undefined, undefined, repository);
            await addMedia(wrapper, [createMedia()]);
            await wrapper.findAll('sw-context-menu-item-stub')[action === 'edit' ? 0 : 2].trigger('click');
            if (action === 'edit') {
                wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Stale title');
            }
            const checkbox =
                action === 'edit'
                    ? '.sw-product-document-form__apply-to-variants'
                    : '.sw-product-document-form__remove-from-variants';
            wrapper.getComponent<ComponentPublicInstance>(checkbox).vm.$emit('update:checked', true);
            await clickButton(wrapper, action === 'edit' ? 'global.default.apply' : 'global.default.remove');
            wrapper.unmount();
            resolveVariants(new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api));
            await flushPromises();
            expect(Array.from(getDocuments()!, (document) => document.title)).toEqual(['manual']);
        });

        it.each([false, true])('allows a new request while a cancelled request settles (reject: %s)', async (rejectOld) => {
            const repository = repositoryFactory.create('product');
            const variants = new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
            let settleOld!: () => void;
            let settleNew!: () => void;
            const search = jest.spyOn(repository, 'search');
            search.mockImplementationOnce(
                () =>
                    new Promise((resolve, reject) => {
                        settleOld = () => (rejectOld ? reject(new Error('Cancelled lookup failed')) : resolve(variants));
                    }),
            );
            search.mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        settleNew = () => resolve(variants);
                    }),
            );
            const wrapper = await createWrapper(undefined, undefined, repository);
            await addMedia(wrapper, [createMedia()]);
            const notification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
            const actionIndex = action === 'edit' ? 0 : 2;
            const button =
                action === 'edit' ? '.sw-product-document-form__save-document' : '.sw-product-document-form__confirm-remove';
            const checkbox =
                action === 'edit'
                    ? '.sw-product-document-form__apply-to-variants'
                    : '.sw-product-document-form__remove-from-variants';
            const label = action === 'edit' ? 'global.default.apply' : 'global.default.remove';

            await wrapper.findAll('sw-context-menu-item-stub')[actionIndex].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>(checkbox).vm.$emit('update:checked', true);
            await clickButton(wrapper, label);
            expect(wrapper.get<HTMLButtonElement>(button).element.disabled).toBe(true);
            await clickButton(wrapper, 'global.default.cancel');
            await wrapper.findAll('sw-context-menu-item-stub')[actionIndex].trigger('click');
            expect(wrapper.get<HTMLButtonElement>(button).element.disabled).toBe(false);
            if (action === 'edit') {
                wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'New title');
            }
            wrapper.getComponent<ComponentPublicInstance>(checkbox).vm.$emit('update:checked', true);
            await clickButton(wrapper, label);
            expect(search).toHaveBeenCalledTimes(2);

            settleOld();
            await flushPromises();

            expect(wrapper.get<HTMLButtonElement>(button).element.disabled).toBe(true);
            expect(getDocuments()).toHaveLength(1);
            expect(getDocuments()![0].title).toBe('manual');
            expect(notification).not.toHaveBeenCalled();

            settleNew();
            await flushPromises();

            expect(wrapper.find(button).exists()).toBe(false);
            expect(Array.from(getDocuments()!, (document) => document.title)).toEqual(
                action === 'edit' ? ['New title'] : [],
            );
        });
    });
});
