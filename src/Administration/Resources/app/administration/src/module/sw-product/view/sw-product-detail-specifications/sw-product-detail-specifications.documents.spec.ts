/**
 * @sw-package inventory
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

type SpecificationsWrapper = VueWrapper<{
    documentsRemoveInheritanceFunction: (
        parentDocuments: EntityCollection<'product_document'>,
    ) => EntityCollection<'product_document'>;
    documentsRestoreInheritanceFunction: () => EntityCollection<'product_document'>;
    onDocumentAddBack: (parentDocument: unknown) => void;
}>;

const EN = 'en-language-id' as EntityKey<'language'>;
const DE = 'de-language-id' as EntityKey<'language'>;

function collection<EntityName extends keyof EntitySchema.Entities>(entity: EntityName, items: unknown[] = []) {
    return new EntityCollection(`/${entity}`, entity, Shopware.Context.api, null, items as Entity<EntityName>[]);
}

function parentDocuments() {
    return collection('product_document', [
        {
            id: 'manual',
            position: 0,
            translations: collection('product_document_translation', [
                { languageId: EN, title: 'Manual', mediaId: 'manual-en' },
                { languageId: DE, title: 'Handbuch', mediaId: 'handbuch-de' },
            ]),
        },
    ]);
}

async function createWrapper() {
    let createdDocuments = 0;

    return mount(await wrapTestComponent('sw-product-detail-specifications', { sync: true }), {
        global: {
            provide: {
                acl: { can: () => true },
                repositoryFactory: {
                    create: (entity: string) => ({
                        create: () => {
                            if (entity !== 'product_document') {
                                return {};
                            }

                            createdDocuments += 1;

                            return {
                                id: `copy-${createdDocuments}`,
                                translations: collection('product_document_translation'),
                            };
                        },
                    }),
                },
            },
            stubs: {
                'mt-card': { template: '<div class="mt-card"><slot name="title" /><slot /></div>' },
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'sw-inheritance-switch': true,
                'sw-help-text': true,
                'sw-skeleton': true,
                'sw-product-measurement-form': true,
                'sw-product-packaging-form': true,
                'sw-product-properties': true,
                'sw-product-document-form': true,
                'sw-product-feature-set-form': true,
                'sw-custom-field-set-renderer': true,
            },
        },
    }) as unknown as SpecificationsWrapper;
}

function documentInheritance(wrapper: SpecificationsWrapper) {
    return wrapper.vm.$refs.productDocumentInheritance as { forceInheritanceRemove: boolean };
}

describe('src/module/sw-product/view/sw-product-detail-specifications documents', () => {
    beforeEach(() => {
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        Object.assign(store, {
            product: {
                id: 'variant-id',
                parentId: 'parent-id',
                isNew: () => false,
                documents: collection('product_document'),
            },
            parentProduct: { id: 'parent-id', documents: parentDocuments() },
        });
    });

    it('copies the parent documents with every language version when the inheritance is removed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const [copy, ...others] = wrapper.vm.documentsRemoveInheritanceFunction(parentDocuments());

        expect(others).toHaveLength(0);
        expect(copy.productId).toBe('variant-id');
        expect(copy.position).toBe(0);
        expect(Array.from(copy.translations, ({ languageId, title, mediaId }) => ({ languageId, title, mediaId }))).toEqual([
            { languageId: EN, title: 'Manual', mediaId: 'manual-en' },
            { languageId: DE, title: 'Handbuch', mediaId: 'handbuch-de' },
        ]);
        expect(documentInheritance(wrapper).forceInheritanceRemove).toBe(true);
    });

    it('drops the copies when the inheritance is restored', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.documentsRemoveInheritanceFunction(parentDocuments());

        const documents = wrapper.vm.documentsRestoreInheritanceFunction();

        expect(documents).toHaveLength(0);
        expect(documentInheritance(wrapper).forceInheritanceRemove).toBe(false);
    });

    it('adds a removed document of the parent back as a copy after the last document', async () => {
        const documents = collection('product_document', [{ id: 'own', position: 3 }]);
        Object.assign(Shopware.Store.get('swProductDetail').product, { documents });
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onDocumentAddBack(parentDocuments().get('manual'));

        const copy = documents.get('copy-1');
        expect(documents).toHaveLength(2);
        expect(copy?.productId).toBe('variant-id');
        expect(copy?.position).toBe(4);
        expect(
            Array.from(copy?.translations ?? [], ({ languageId, title, mediaId }) => ({ languageId, title, mediaId })),
        ).toEqual([
            { languageId: EN, title: 'Manual', mediaId: 'manual-en' },
            { languageId: DE, title: 'Handbuch', mediaId: 'handbuch-de' },
        ]);
    });
});
