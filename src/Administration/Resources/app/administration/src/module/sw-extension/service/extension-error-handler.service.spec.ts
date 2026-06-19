import extensionErrorHandler from './extension-error-handler.service';

const currentDownloadNotAllowedForDomainDocumentationLink =
    'https://docs.shopware.com/en/shopware-6-en/extensions/error-messages#download-not-allowed-for-domain';
const currentGermanDownloadNotAllowedForDomainDocumentationLink =
    'https://docs.shopware.com/de/shopware-6-de/Erweiterungen/fehlermeldungen#download-not-allowed-for-domain';

function createStoreApiError(documentationLink: string): StoreApiException {
    return {
        code: 'FRAMEWORK__STORE_ERROR',
        status: '500',
        title: 'Download not allowed',
        detail: 'The download of the extension is not allowed for the selected shop.',
        meta: {
            documentationLink,
        },
    } as StoreApiException;
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/service/extension-error-handler.service', () => {
    it.each([
        [
            'https://docs.shopware.com/de/shopware-6-de/einstellungen/Erweiterungen/fehlermeldungen#download-not-allowed-for-domain',
            currentGermanDownloadNotAllowedForDomainDocumentationLink,
        ],
        [
            'https://docs.shopware.com/en/shopware-6-en/settings/extensions/error-messages#download-not-allowed-for-domain',
            currentDownloadNotAllowedForDomainDocumentationLink,
        ],
    ])('maps the outdated download-not-allowed documentation link', (documentationLink, expectedDocumentationLink) => {
        const mappedErrors = extensionErrorHandler.mapErrors([
            createStoreApiError(documentationLink),
        ]);

        expect(mappedErrors).toEqual([
            {
                title: 'Download not allowed',
                message: 'The download of the extension is not allowed for the selected shop.',
                details: null,
                parameters: {
                    documentationLink: expectedDocumentationLink,
                },
            },
        ]);
    });

    it('keeps unknown documentation links unchanged', () => {
        const documentationLink = 'https://docs.shopware.com/en/shopware-6-en/extensions';
        const mappedErrors = extensionErrorHandler.mapErrors([
            createStoreApiError(documentationLink),
        ]);

        expect(mappedErrors[0]?.parameters?.documentationLink).toBe(documentationLink);
    });
});
