/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import {
    mockSnackbar,
    mockVariantSync,
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    addDocuments,
    clickButton,
    getDocuments,
} from './helpers';

const dialogs = {
    edit: {
        menuIndex: 0,
        checkbox: '.sw-product-document-form__apply-to-variants',
        confirm: '.sw-product-document-form__save-document',
        operation: (mediaId: string) => ({ mediaId, title: 'manual', changes: { title: 'New title' } }),
        appliedTitles: ['New title'],
    },
    remove: {
        menuIndex: 2,
        checkbox: '.sw-product-document-form__remove-from-variants',
        confirm: '.sw-product-document-form__confirm-remove',
        operation: (mediaId: string) => ({ mediaId, title: 'manual' }),
        appliedTitles: [],
    },
} as const;

describe('sw-product-document-form dialog sessions', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        mockSnackbar.mockClear();
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
        jest.restoreAllMocks();
        cleanupWrappers();
    });

    it.each([
        [1, 1, false],
        [1, 2, true],
        [2, 2, false],
    ] as const)(
        'after %s of %s successful uploads, keeps the upload dialog open: %s',
        async (successAmount, totalAmount, staysOpen) => {
            const repository = repositoryFactory.create('media');
            const media = createMedia();
            jest.spyOn(repository, 'get').mockResolvedValue(media);
            const wrapper = await createWrapper(repository);
            await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');

            wrapper.getComponent<ComponentPublicInstance>('sw-upload-listener-stub').vm.$emit('media-upload-finish', {
                targetId: media.id,
                successAmount,
                totalAmount,
            });
            await flushPromises();

            expect(getDocuments()?.[0].mediaId).toBe(media.id);
            expect(wrapper.find('.sw-product-document-form__upload-modal').exists()).toBe(staysOpen);
        },
    );

    function mockLateMedia(reject = false) {
        const repository = repositoryFactory.create('media');
        const media = createMedia();
        let settle!: () => void;
        jest.spyOn(repository, 'get').mockImplementationOnce(
            () =>
                new Promise((resolve, rejectRequest) => {
                    settle = () => (reject ? rejectRequest(new Error('Late upload')) : resolve(media));
                }),
        );

        return { repository, media, settle: () => settle() };
    }

    async function finishUpload(wrapper: VueWrapper, mediaId: string) {
        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: mediaId });
        await flushPromises();
    }

    it('adds the uploaded document to the same product when its media load resolves after the form unmounted', async () => {
        const { repository, media, settle } = mockLateMedia();
        const wrapper = await createWrapper(repository);
        await finishUpload(wrapper, media.id);
        wrapper.unmount();
        settle();
        await flushPromises();

        expect(getDocuments()).toHaveLength(1);
        expect(getDocuments()?.first()?.mediaId).toBe(media.id);
    });

    it('does not add the uploaded document when another product was loaded before its media load resolves', async () => {
        const { repository, media, settle } = mockLateMedia();
        const wrapper = await createWrapper(repository);
        const product = Shopware.Store.get('swProductDetail').product;
        await finishUpload(wrapper, media.id);
        Shopware.Store.get('swProductDetail').product = createProduct();
        settle();
        await flushPromises();

        expect(product.documents).toHaveLength(0);
        expect(getDocuments()).toHaveLength(0);
    });

    it('shows an error when the media load fails after the form unmounted', async () => {
        const { repository, media, settle } = mockLateMedia(true);
        const wrapper = await createWrapper(repository);
        const notification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        await finishUpload(wrapper, media.id);
        wrapper.unmount();
        settle();
        await flushPromises();

        expect(getDocuments()).toHaveLength(0);
        expect(mockSnackbar).not.toHaveBeenCalled();
        expect(notification.mock.calls.map(([config]) => config)).toEqual([
            expect.objectContaining({ variant: 'error', message: 'sw-product.documentForm.errorLoadMedia' }),
        ]);
    });

    it('adds the uploaded document after another document editor is opened and cancelled', async () => {
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
        await addDocuments(wrapper, [createMedia()]);
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

    describe.each(['edit', 'remove'] as const)('%s dialog', (action) => {
        const dialog = dialogs[action];

        async function confirmWithPendingVariantCheck(wrapper: VueWrapper) {
            let settle!: (variantIds: string[] | Error) => void;
            mockVariantSync.mockImplementationOnce(
                () =>
                    new Promise((resolve, reject) => {
                        settle = (result) => (result instanceof Error ? reject(result) : resolve(result));
                    }),
            );
            await wrapper.findAll('sw-context-menu-item-stub')[dialog.menuIndex].trigger('click');
            if (action === 'edit') {
                await wrapper.get('.sw-product-document-form__name-input input').setValue('New title');
            }
            expect(wrapper.get<HTMLInputElement>(`${dialog.checkbox} input`).element.checked).toBe(true);
            await wrapper.get(dialog.confirm).trigger('click');
            return (result: string[] | Error) => settle(result);
        }

        it('emits and queues nothing when another product was loaded before the variant check returned', async () => {
            const store = Shopware.Store.get('swProductDetail');
            const wrapper = await createWrapper();
            await addDocuments(wrapper, [createMedia()]);
            const product = store.product;
            const document = getDocuments()![0];

            const settle = await confirmWithPendingVariantCheck(wrapper);
            store.product = createProduct();
            await flushPromises();
            expect(wrapper.find(dialog.confirm).exists()).toBe(false);

            settle(['variant-a']);
            await flushPromises();

            expect(product.documents).toHaveLength(1);
            expect(document.title).toBe('manual');
            expect(store.product.documents).toHaveLength(0);
            expect(store.documentVariantOperations).toEqual({});
            expect(mockSnackbar).not.toHaveBeenCalled();
        });

        it('queues nothing and reports no success when the variant check returns after the form unmounted', async () => {
            const store = Shopware.Store.get('swProductDetail');
            const wrapper = await createWrapper();
            await addDocuments(wrapper, [createMedia()]);
            const document = getDocuments()![0];

            const settle = await confirmWithPendingVariantCheck(wrapper);
            expect(mockVariantSync).toHaveBeenCalledTimes(1);
            wrapper.unmount();
            settle(['variant-a']);
            await flushPromises();

            expect(getDocuments()).toHaveLength(1);
            expect(document.title).toBe('manual');
            expect(store.documentVariantOperations).toEqual({});
            expect(mockSnackbar).not.toHaveBeenCalled();
        });
    });
});
