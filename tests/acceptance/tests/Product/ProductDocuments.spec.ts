import { test, expect, getLanguageData, getSnippetSetId } from '@fixtures/AcceptanceTest';
import type { FixtureTypes } from '@fixtures/AcceptanceTest';

// Adds a German language and its own (path-based) storefront domain to the worker sales channel.
async function enableGermanStorefront(TestDataService: FixtureTypes['TestDataService']): Promise<void> {
    const deLanguage = await getLanguageData(TestDataService.AdminApiClient, 'de-DE');
    const deSnippetSetId = await getSnippetSetId(TestDataService.AdminApiClient, 'de-DE');
    await TestDataService.assignSalesChannelLanguage(TestDataService.defaultSalesChannel.id, deLanguage.id);
    await TestDataService.createSalesChannelDomain({
        languageId: deLanguage.id,
        snippetSetId: deSnippetSetId,
        url: `${(process.env.APP_URL ?? '').replace(/\/$/, '')}/test-${TestDataService.defaultSalesChannel.id}/de-DE/`,
    });
    await TestDataService.clearCaches();
}

async function getDocumentMediaId(
    TestDataService: FixtureTypes['TestDataService'],
    productId: string,
): Promise<string | undefined> {
    const response = await TestDataService.AdminApiClient.post('search/product-document', {
        data: {
            filter: [{ type: 'equals', field: 'productId', value: productId }],
            includes: { product_document: ['id', 'mediaId'] },
            limit: 1,
        },
    });
    const { data } = (await response.json()) as { data: { mediaId?: string }[] };
    return data[0]?.mediaId;
}

test(
    'As a merchant, I want to manage product documents.',
    { tag: '@Product' },
    async ({
        TestDataService,
        ShopAdmin,
        SaveProduct,
        UploadDocument,
        RenameDocument,
        ReplaceDocument,
        SortDocument,
        DeleteDocument,
        AdminProductDetail,
        IdProvider,
    }) => {
        test.slow();

        const product = await TestDataService.createBasicProduct();
        const documentId = IdProvider.getIdPair().id;
        const documentName = `document-${documentId}`;
        const renamedDocumentName = `renamed-document-${documentId}`;
        const secondDocumentName = `document2-${documentId}`;

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));

        await ShopAdmin.attemptsTo(UploadDocument(documentName));
        await ShopAdmin.attemptsTo(SaveProduct());
        await ShopAdmin.expects(AdminProductDetail.documentLink(documentName)).toBeVisible();

        await ShopAdmin.attemptsTo(RenameDocument(documentName, renamedDocumentName));
        await ShopAdmin.attemptsTo(SaveProduct());
        await ShopAdmin.expects(AdminProductDetail.documentLink(renamedDocumentName)).toBeVisible();

        // Replacing the file must point the document at a different media.
        const mediaIdBeforeReplace = await getDocumentMediaId(TestDataService, product.id);
        expect(mediaIdBeforeReplace, 'the document should have a media before replacing it').toBeTruthy();
        await ShopAdmin.attemptsTo(ReplaceDocument(renamedDocumentName, { sizeInBytes: 2 * 1024 * 1024 }));
        await ShopAdmin.attemptsTo(SaveProduct());
        await ShopAdmin.expects(AdminProductDetail.documentLink(renamedDocumentName)).toBeVisible();
        const mediaIdAfterReplace = await getDocumentMediaId(TestDataService, product.id);
        expect(mediaIdAfterReplace).not.toBe(mediaIdBeforeReplace);

        // Move the second document above the first.
        await ShopAdmin.attemptsTo(UploadDocument(secondDocumentName));
        await ShopAdmin.attemptsTo(SaveProduct());
        await ShopAdmin.attemptsTo(SortDocument(secondDocumentName, 'up'));
        await ShopAdmin.attemptsTo(SaveProduct());
        await ShopAdmin.expects(AdminProductDetail.documentTitles).toHaveText([secondDocumentName, renamedDocumentName]);

        // Reload to verify the order persisted.
        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        await ShopAdmin.expects(AdminProductDetail.documentTitles).toHaveText([secondDocumentName, renamedDocumentName]);

        await ShopAdmin.attemptsTo(DeleteDocument(renamedDocumentName));
        await ShopAdmin.attemptsTo(SaveProduct());
        await ShopAdmin.expects(AdminProductDetail.documentLink(renamedDocumentName)).not.toBeVisible();
    },
);

test(
    'As a merchant, I want to upload product documents in multiple languages and switch between them in the Administration.',
    { tag: '@Product' },
    async ({
        TestDataService,
        ShopAdmin,
        SaveProduct,
        UploadDocument,
        AddDocumentLanguageVersion,
        SwitchAdminLanguage,
        AdminProductDetail,
        IdProvider,
    }) => {
        test.slow();

        const product = await TestDataService.createBasicProduct();
        const documentId = IdProvider.getIdPair().id;
        const documentNameEN = `document-en-${documentId}`;
        const documentNameDE = `document-de-${documentId}`;

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));

        // A document is added only in the default language; other languages override its title/file.
        await ShopAdmin.attemptsTo(UploadDocument(documentNameEN));
        await ShopAdmin.attemptsTo(SaveProduct());
        await ShopAdmin.expects(AdminProductDetail.documentLink(documentNameEN)).toBeVisible();

        // In German the document is still listed (inherited); override its title via "Add German version".
        await ShopAdmin.attemptsTo(SwitchAdminLanguage('de-DE'));
        await ShopAdmin.expects(AdminProductDetail.documentLink(documentNameEN)).toBeVisible();
        await ShopAdmin.attemptsTo(AddDocumentLanguageVersion(documentNameEN, documentNameDE, 'German'));
        await ShopAdmin.attemptsTo(SaveProduct());

        await ShopAdmin.expects(AdminProductDetail.documentLink(documentNameDE)).toBeVisible();
        await ShopAdmin.expects(AdminProductDetail.documentLink(documentNameEN)).not.toBeVisible();

        await ShopAdmin.attemptsTo(SwitchAdminLanguage('en-GB'));
        await ShopAdmin.expects(AdminProductDetail.documentLink(documentNameEN)).toBeVisible();
        await ShopAdmin.expects(AdminProductDetail.documentLink(documentNameDE)).not.toBeVisible();
    },
);

test(
    'As a customer, I see the default-language documents when the active language has none (fallback).',
    { tag: ['@Product', '@Storefront'] },
    async ({ TestDataService, ShopCustomer, StorefrontProductDetail, SwitchStorefrontLanguage, IdProvider }) => {
        test.slow();

        const product = await TestDataService.createBasicProduct();
        const documentId = IdProvider.getIdPair().id;
        const documentNameEN = `document-en-${documentId}`;

        // Only a default-language (English) document exists for the product.
        await TestDataService.createProductDocument(product.id, documentNameEN, 'en-GB');
        await enableGermanStorefront(TestDataService);

        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        // Documents live in their own tab; open it first.
        await ShopCustomer.presses(StorefrontProductDetail.documentsTab);
        await ShopCustomer.expects(StorefrontProductDetail.documentLink(documentNameEN)).toBeVisible();

        // German has no own documents, so it falls back to English. Retry: the new domain can lag the cache.
        await ShopCustomer.expects(async () => {
            await ShopCustomer.attemptsTo(SwitchStorefrontLanguage('de-DE'));
            await ShopCustomer.presses(StorefrontProductDetail.documentsTab);
            await ShopCustomer.expects(StorefrontProductDetail.documentLink(documentNameEN)).toBeVisible();
        }).toPass({ intervals: [1_000, 2_500, 5_000] });
    },
);

test(
    "As a merchant, I want variant products to inherit the parent's documents and to be able to override them.",
    { tag: '@Product' },
    async ({
        TestDataService,
        ShopAdmin,
        SaveProduct,
        UploadDocument,
        DisableDocumentInheritance,
        AdminProductDetail,
        IdProvider,
    }) => {
        test.slow();

        const product = await TestDataService.createBasicProduct();
        const documentId = IdProvider.getIdPair().id;
        const documentNameEN = `document-${documentId}`;

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        await ShopAdmin.attemptsTo(UploadDocument(documentNameEN));
        await ShopAdmin.attemptsTo(SaveProduct());

        // A variant inherits the parent's documents.
        const propertyGroup = await TestDataService.createColorPropertyGroup();
        const [variantProduct] = await TestDataService.createVariantProducts(product, [propertyGroup]);
        await ShopAdmin.goesTo(AdminProductDetail.url(variantProduct.id));
        await ShopAdmin.expects(AdminProductDetail.documentLink(documentNameEN)).toBeVisible();

        // Turning off inheritance lets the variant manage its own documents.
        await ShopAdmin.attemptsTo(DisableDocumentInheritance());

        const variantDocumentId = IdProvider.getIdPair().id;
        const variantDocumentNameEN = `variant-document-${variantDocumentId}`;
        await ShopAdmin.attemptsTo(UploadDocument(variantDocumentNameEN));
        await ShopAdmin.attemptsTo(SaveProduct());
        await ShopAdmin.expects(AdminProductDetail.documentLink(variantDocumentNameEN)).toBeVisible();
    },
);

test(
    'As a merchant, I want uploaded product documents to be validated for file type and size.',
    { tag: '@Product' },
    async ({ TestDataService, ShopAdmin, UploadDocument, AdminProductDetail, IdProvider }) => {
        test.slow();

        const product = await TestDataService.createBasicProduct();
        const documentId = IdProvider.getIdPair().id;
        const documentName = `document-${documentId}`;
        const oversizedBytes = 21 * 1024 * 1024; // above the 20 MB limit

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));

        // Unsupported file type: an error shows and nothing is added.
        await ShopAdmin.attemptsTo(UploadDocument(documentName, { type: 'txt' }));
        await ShopAdmin.expects(AdminProductDetail.documentUploadError).toBeVisible();
        await ShopAdmin.expects(AdminProductDetail.documentTitles).toHaveCount(0);

        // Oversized file. The empty grid is the real proof it was rejected, not just the message.
        await ShopAdmin.attemptsTo(UploadDocument(documentName, { sizeInBytes: oversizedBytes }));
        await ShopAdmin.expects(AdminProductDetail.documentUploadError).toBeVisible();
        await ShopAdmin.expects(AdminProductDetail.documentTitles).toHaveCount(0);

        // There is no per-product document limit, so there is no max-count case.
    },
);

test(
    'As a customer, I want to download product documents from the storefront in multiple languages.',
    { tag: ['@Product', '@Storefront'] },
    async ({
        TestDataService,
        ShopCustomer,
        StorefrontProductDetail,
        DownloadDocument,
        SwitchStorefrontLanguage,
        IdProvider,
    }) => {
        test.slow();

        const product = await TestDataService.createBasicProduct();
        const documentId = IdProvider.getIdPair().id;
        const documentNameEN = `document-en-${documentId}`;
        const documentNameDE = `document-de-${documentId}`;

        // One document, English base + German override, each with a distinctly sized file so the download
        // can be checked to serve the right language.
        const { sizeByLocale } = await TestDataService.createProductDocumentWithTranslations(product.id, {
            'en-GB': documentNameEN,
            'de-DE': documentNameDE,
        });
        await enableGermanStorefront(TestDataService);

        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.presses(StorefrontProductDetail.documentsTab);

        await ShopCustomer.expects(StorefrontProductDetail.documentLink(documentNameEN)).toBeVisible();
        await ShopCustomer.attemptsTo(DownloadDocument(documentNameEN, { expectedSizeBytes: sizeByLocale['en-GB'] }));

        // German serves the override. Retry: the new domain can lag the cache.
        await ShopCustomer.expects(async () => {
            await ShopCustomer.attemptsTo(SwitchStorefrontLanguage('de-DE'));
            await ShopCustomer.presses(StorefrontProductDetail.documentsTab);
            await ShopCustomer.expects(StorefrontProductDetail.documentLink(documentNameDE)).toBeVisible();
        }).toPass({ intervals: [1_000, 2_500, 5_000] });
        await ShopCustomer.expects(StorefrontProductDetail.documentLink(documentNameEN)).not.toBeVisible();
        await ShopCustomer.attemptsTo(DownloadDocument(documentNameDE, { expectedSizeBytes: sizeByLocale['de-DE'] }));
    },
);
