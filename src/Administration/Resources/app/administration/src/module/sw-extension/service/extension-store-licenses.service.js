import ApiService from 'src/core/service/api.service';

export default class ExtensionLicenseService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'plugin') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'extensionStoreLicensesService';
    }

    async getLicensedExtensions(context) {
        const { data } = await this.httpClient.get('_action/extension/licensed', {
            headers: this.basicHeaders(context),
            version: 3
        });

        const licensedExtensions = data.data;
        licensedExtensions.total = data.meta.total;

        return licensedExtensions;
    }

    async purchaseExtension(extensionId, variantId, tocAccepted, permissionsAccepted) {
        await this.httpClient.post(
            '_action/extension/purchase',
            { extensionId, variantId, tocAccepted, permissionsAccepted },
            {
                headers: this.basicHeaders(),
                version: 3
            }
        );
    }

    basicHeaders(context = null) {
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`
        };

        if (context && context.languageId) {
            headers['sw-language-id'] = context.languageId;
        }

        return headers;
    }
}
