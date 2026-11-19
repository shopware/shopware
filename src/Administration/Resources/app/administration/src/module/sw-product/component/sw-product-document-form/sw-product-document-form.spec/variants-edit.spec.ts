/**
 * @sw-package inventory
 */
import { config } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import { getDocumentMedia } from '../product-document.helper';

import {
    mockSnackbar,
    mockVariantSync,
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    clickButton,
    selectMedia,
    addDocuments,
    getDocuments,
} from './helpers';

describe('sw-product-document-form variant editing', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    const notification = mockSnackbar;
    const translate = jest.fn((key: unknown) => key);

    beforeEach(() => {
        notification.mockClear();
        translate.mockClear();
        jest.spyOn(config.global.mocks, '$t').mockImplementation(translate);
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        Object.assign(store, { product: createProduct() });
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
        jest.restoreAllMocks();
    });

    describe('applying document edits to variants', () => {
        it('dry runs the variant update and queues it for the product save instead of staging variant copies', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 5;
            const original = createMedia();
            const replacement = createMedia('replacement');
            const productRepository = repositoryFactory.create('product');
            const searchSpy = jest.spyOn(productRepository, 'search');
            const saveSpy = jest.spyOn(productRepository, 'save');
            mockVariantSync.mockResolvedValueOnce(['variant-a', 'variant-b']);
            const wrapper = await createWrapper(undefined, undefined, productRepository);
            await addDocuments(wrapper, [original]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            await selectMedia(wrapper, [replacement]);
            wrapper
                .getComponent<ComponentPublicInstance>('.mt-text-field')
                .vm.$emit('update:modelValue', 'Battery safety data sheet');
            expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__apply-to-variants input').element.checked).toBe(
                true,
            );
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            const operation = {
                mediaId: original.id,
                title: 'manual',
                changes: { title: 'Battery safety data sheet', mediaId: replacement.id },
            };
            expect(mockVariantSync).toHaveBeenCalledTimes(1);
            expect(mockVariantSync).toHaveBeenCalledWith(store.product.id, [operation], true);
            expect(store.documentVariantOperations).toEqual({ [getDocuments()![0].id]: operation });
            expect(getDocuments()?.[0]).toMatchObject({
                title: 'Battery safety data sheet',
                mediaId: replacement.id,
                translated: { title: 'Battery safety data sheet', mediaId: replacement.id },
            });
            expect(wrapper.find('.sw-product-document-form__edit-content').exists()).toBe(false);
            expect(store.product.children).toHaveLength(0);
            const payload = new ChangesetGenerator().generate(store.product);
            expect(payload.deletionQueue).toHaveLength(0);
            expect(payload.changes).not.toHaveProperty('children');
            expect(payload.changes).toHaveProperty('documents', [
                expect.objectContaining({
                    id: getDocuments()?.[0].id,
                    title: 'Battery safety data sheet',
                    mediaId: replacement.id,
                }),
            ]);
            expect(notification).toHaveBeenCalledTimes(1);
            expect(notification).toHaveBeenCalledWith(
                expect.objectContaining({
                    variant: 'success',
                    message: 'sw-product.documentForm.applySuccess',
                }),
            );
            expect(translate).toHaveBeenCalledWith('sw-product.documentForm.applySuccess', {
                name: 'Battery safety data sheet',
                count: 2,
            });
            expect(searchSpy).not.toHaveBeenCalled();
            expect(saveSpy).not.toHaveBeenCalled();
        });

        it('neither dry runs, queues nor reports a variant update for an unchanged document', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const wrapper = await createWrapper();
            await addDocuments(wrapper, [createMedia()]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            expect(wrapper.find('.sw-product-document-form__edit-content').exists()).toBe(false);
            expect(mockVariantSync).not.toHaveBeenCalled();
            expect(store.documentVariantOperations).toEqual({});
            expect(notification).not.toHaveBeenCalled();
            expect(translate).not.toHaveBeenCalledWith('sw-product.documentForm.applySuccess', expect.anything());
            expect(store.product.children).toHaveLength(0);
        });

        it('keeps variant documents unchanged when the checkbox is off', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const wrapper = await createWrapper();
            await addDocuments(wrapper, [createMedia()]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Parent only');
            await wrapper.get('.sw-product-document-form__apply-to-variants input').setValue(false);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            expect(mockVariantSync).not.toHaveBeenCalled();
            expect(store.documentVariantOperations).toEqual({});
            expect(store.product.children).toHaveLength(0);
            expect(notification).not.toHaveBeenCalledWith(expect.objectContaining({ variant: 'success' }));
            expect(getDocuments()?.[0].title).toBe('Parent only');
        });

        it('resets the variant option to checked on reopening without applying cancelled edits', async () => {
            Shopware.Store.get('swProductDetail').product.childCount = 1;
            const wrapper = await createWrapper();
            await addDocuments(wrapper, [createMedia()]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-product-document-form__apply-to-variants input').setValue(false);
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Cancelled title');

            await clickButton(wrapper, 'global.default.cancel');
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');

            expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__apply-to-variants input').element.checked).toBe(
                true,
            );
            expect(getDocuments()?.[0].title).toBe('manual');
            expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input').element.value).toBe(
                'manual',
            );
        });

        it('keeps one variant update keyed by the saved file when a replacement is applied and reverted before saving', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const original = createMedia();
            const replacement = createMedia('replacement');
            mockVariantSync.mockResolvedValueOnce(['variant-a']).mockResolvedValueOnce(['variant-a']);
            const wrapper = await createWrapper();
            await addDocuments(wrapper, [original]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            await selectMedia(wrapper, [replacement]);
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'manual');
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();
            expect(translate).toHaveBeenCalledWith('sw-product.documentForm.applySuccess', { name: 'manual', count: 1 });
            translate.mockClear();
            notification.mockClear();

            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            await selectMedia(wrapper, [original]);
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            const replaceOperation = { mediaId: original.id, title: 'manual', changes: { mediaId: replacement.id } };
            const revertOperation = { mediaId: original.id, title: 'manual', changes: { mediaId: original.id } };
            expect(mockVariantSync).toHaveBeenCalledTimes(2);
            expect(mockVariantSync).toHaveBeenNthCalledWith(1, store.product.id, [replaceOperation], true);
            expect(mockVariantSync).toHaveBeenLastCalledWith(store.product.id, [revertOperation], true);
            expect(store.documentVariantOperations).toEqual({ [getDocuments()![0].id]: revertOperation });
            expect(getDocuments()?.[0]).toMatchObject({ mediaId: original.id, translated: { mediaId: original.id } });
            expect(notification).toHaveBeenCalledTimes(1);
            expect(notification).toHaveBeenCalledWith({
                variant: 'success',
                message: 'sw-product.documentForm.applySuccess',
            });
            expect(translate).toHaveBeenCalledWith('sw-product.documentForm.applySuccess', { name: 'manual', count: 1 });
        });

        it('keeps the editor open with Cancel disabled until the variant dry run resolves', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const media = createMedia();
            let resolveSync!: (variantIds: string[]) => void;
            mockVariantSync.mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveSync = resolve;
                    }),
            );
            const wrapper = await createWrapper();
            await addDocuments(wrapper, [media]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'New title');
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            const cancel = wrapper.findAll('button').find((button) => button.text() === 'global.default.cancel')!;
            expect(cancel.attributes('disabled')).toBeDefined();
            expect(wrapper.get('.sw-product-document-form__save-document').attributes('disabled')).toBeDefined();
            await cancel.trigger('click');
            expect(wrapper.find('.sw-product-document-form__edit-content').exists()).toBe(true);
            expect(getDocuments()?.[0].title).toBe('manual');
            expect(store.documentVariantOperations).toEqual({});
            expect(notification).not.toHaveBeenCalled();

            resolveSync(['variant-a']);
            await flushPromises();

            expect(wrapper.find('.sw-product-document-form__edit-content').exists()).toBe(false);
            expect(getDocuments()?.[0].title).toBe('New title');
            expect(store.documentVariantOperations).toEqual({
                [getDocuments()![0].id]: { mediaId: media.id, title: 'manual', changes: { title: 'New title' } },
            });
            expect(translate).toHaveBeenCalledWith('sw-product.documentForm.applySuccess', {
                name: 'New title',
                count: 1,
            });
        });

        it('keeps the original document and editor open when loading variants fails', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
            mockVariantSync.mockRejectedValueOnce(new Error('Load failed'));
            const wrapper = await createWrapper();
            await addDocuments(wrapper, [createMedia()]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'New title');
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            expect(getDocuments()?.[0].title).toBe('manual');
            expect(store.documentVariantOperations).toEqual({});
            expect(wrapper.get('.sw-product-document-form__save-document').attributes('disabled')).toBeUndefined();
            expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input').element.value).toBe(
                'New title',
            );
            expect(notificationSpy).toHaveBeenCalledWith(
                expect.objectContaining({ variant: 'error', message: 'sw-product.documentForm.errorLoadVariants' }),
            );
            expect(notification).not.toHaveBeenCalledWith(expect.objectContaining({ variant: 'success' }));
        });

        it('applies the file picked before the variant dry run when an upload finishes while it runs', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const original = createMedia();
            const replacement = createMedia('replacement');
            const uploaded = createMedia('uploaded');
            const mediaRepository = repositoryFactory.create('media');
            let finishDryRun!: (variantIds: string[]) => void;
            mockVariantSync.mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishDryRun = resolve;
                    }),
            );
            const wrapper = await createWrapper(mediaRepository);
            await addDocuments(wrapper, [original]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            await selectMedia(wrapper, [replacement]);
            await clickButton(wrapper, 'global.default.apply');

            jest.spyOn(mediaRepository, 'get').mockResolvedValueOnce(uploaded);
            wrapper
                .findAllComponents<ComponentPublicInstance>('sw-upload-listener-stub')
                .at(-1)!
                .vm.$emit('media-upload-finish', { targetId: uploaded.id });
            await flushPromises();
            finishDryRun(['variant-a']);
            await flushPromises();

            const document = getDocuments()![0];
            expect(document.mediaId).toBe(replacement.id);
            expect(getDocumentMedia(document)?.id).toBe(replacement.id);
            expect(store.documentVariantOperations).toEqual({
                [document.id]: { mediaId: original.id, title: 'manual', changes: { mediaId: replacement.id } },
            });
        });
    });
});
