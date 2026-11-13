/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import EntityFactory from 'src/core/data/entity-factory.data';
import fileValidationService from 'src/app/service/file-validation.service';

const wrappers: VueWrapper[] = [];
/** @private */
export function cleanupWrappers(): void {
    wrappers.splice(0).forEach((wrapper) => {
        if (wrapper.exists()) {
            wrapper.unmount();
        }
    });
}

/** @private */
export function createProduct(id?: EntityKey<'product'>) {
    return withProductStates(repositoryFactory.create('product').create(Shopware.Context.api, id));
}

/** @private */
export function withProductStates(product: Entity<'product'>) {
    // Document fixtures have no legacy download states; the detail store expects a string array.
    const states: string[] = [];
    Object.assign(product.getOrigin(), { states });
    return Object.assign(product, { states });
}

/** @private */
export async function createWrapper(
    mediaRepository = repositoryFactory.create('media'),
    mediaService = { prepareDownloadMedia: jest.fn(), downloadMedia: jest.fn() },
    productRepository = repositoryFactory.create('product'),
    options: { baseView?: boolean; isInherited?: boolean; getDefaultFolderId?: () => Promise<string | null> } = {},
) {
    if (options.isInherited) {
        const store = Shopware.Store.get('swProductDetail');
        store.product.parentId = store.parentProduct.id;
    }

    const documentForm = await wrapTestComponent('sw-product-document-form', { sync: true });
    const component = options.baseView ? await wrapTestComponent('sw-product-detail-base', { sync: true }) : documentForm;
    const wrapper = shallowMount(component, {
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $route: { name: 'sw.product.detail.base', params: { id: Shopware.Store.get('swProductDetail').product.id } },
            },
            directives: { droppable: {} },
            provide: {
                mediaDefaultFolderService: {
                    getDefaultFolderId: jest.fn().mockResolvedValue(null),
                },
                mediaService: {
                    addListener: jest.fn(),
                    removeListener: jest.fn(),
                    removeByTag: jest.fn(),
                    getDefaultFolderId: options.getDefaultFolderId ?? jest.fn().mockResolvedValue(null),
                    ...mediaService,
                },
                mediaPresignedUploadService: {},
                fileValidationService: fileValidationService(),
                entityFactory: new EntityFactory(),
                repositoryFactory: {
                    create: (name: keyof EntitySchema.EntityKeys) => {
                        if (name === 'media') {
                            return mediaRepository;
                        }
                        if (options.baseView && name === 'media_default_folder') {
                            return { search: jest.fn().mockResolvedValue({ first: () => ({}) }) };
                        }
                        return name === 'product' ? productRepository : repositoryFactory.create(name);
                    },
                },
            },
            stubs: {
                'mt-badge': { template: '<span class="mt-badge"><slot /></span>' },
                ...(options.baseView ? { 'sw-product-document-form': documentForm } : {}),
                'sw-product-document-download': await wrapTestComponent('sw-product-document-download', { sync: true }),

                'mt-floating-ui': {
                    props: ['isOpened'],
                    template: '<div><slot name="trigger"/><slot v-if="isOpened"/></div>',
                },
                'sw-product-document-empty-state': await wrapTestComponent('sw-product-document-empty-state', {
                    sync: true,
                }),

                'sw-container': { template: '<div><slot/></div>' },
                'router-link': true,
                'mt-icon': { template: '<span class="mt-icon" />' },
                'mt-base-field': false,
                'mt-field-error': false,
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'mt-card': {
                    template: '<section><slot name="title"/><slot name="headerRight"/><slot name="grid"/></section>',
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `<div><div v-for="(item, itemIndex) in dataSource" :key="item.id" class="document-row">
                        <slot name="column-title" v-bind="{ item }"/>
                        <slot name="column-fileName" v-bind="{ item }"/>
                        <slot name="column-fileSize" v-bind="{ item }"/>
                        <slot name="column-position" v-bind="{ item, itemIndex }"/>
                        <slot name="actions" v-bind="{ item }"/>
                    </div></div>`,
                },
                'mt-modal-root': { template: '<div><slot/></div>' },
                'mt-modal': { template: '<div class="upload-modal"><slot/><slot name="footer"/></div>' },
                'sw-modal': { template: '<div class="modal"><slot/><slot name="modal-footer"/></div>' },
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch', { sync: true }),
                'i18n-t': { template: '<span><slot name="language"/></span>' },
                'sw-upload-listener': true,
                'sw-media-upload-v2': await wrapTestComponent('sw-media-upload-v2', { sync: true }),
                'sw-media-preview-v2': true,
                'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                'sw-media-modal-v2': true,
                'sw-context-menu-item': true,
                'sw-button-group': { template: '<span><slot/></span>' },
            },
        },
    });
    wrappers.push(wrapper);
    return wrapper;
}

/** @private */
export function createMedia(name = 'manual') {
    const media = repositoryFactory.create('media').create();
    Object.assign(media, { fileName: name, fileExtension: 'pdf', fileSize: 1024, private: false });
    media._isNew = false;
    return media;
}

/** @private */
export async function clickButton(wrapper: VueWrapper, text: string) {
    const button = wrapper.findAll('button').find((item) => item.text() === text);
    if (!button) {
        throw new Error(`Button not found: ${text}`);
    }
    await button.trigger('click');
}

/** @private */
export async function selectMedia(wrapper: VueWrapper, media: Entity<'media'>[]) {
    wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').vm.$emit('media-upload-sidebar-open');
    await flushPromises();
    wrapper.getComponent<ComponentPublicInstance>('sw-media-modal-v2-stub').vm.$emit('media-modal-selection-change', media);
    await flushPromises();
}

/** @private */
export async function addMedia(wrapper: VueWrapper, media: Entity<'media'>[]) {
    await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
    await selectMedia(wrapper, media);
}

/** @private */
export function getDocuments() {
    return Shopware.Store.get('swProductDetail').product.documents;
}
