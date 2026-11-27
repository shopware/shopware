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
                type: 'update',
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
                [document.id]: {
                    type: 'update',
                    mediaId: original.id,
                    title: 'manual',
                    changes: { mediaId: replacement.id },
                },
            });
        });
    });
});
