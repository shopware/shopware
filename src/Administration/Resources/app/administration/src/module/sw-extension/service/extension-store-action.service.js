import ApiService from 'src/core/service/api.service';

export default class ExtensionStoreActionService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'extension') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'extensionStoreActionService';
    }

    downloadExtension(technicalName) {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/download/${technicalName}`, {}, {
                headers: this.basicHeaders(Shopware.Context.api),
                version: 3,
            });
    }

    installExtension(technicalName, type) {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/install/${type}/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3,
            });
    }

    updateExtension(technicalName, type) {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/update/${type}/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3,
            });
    }

    activateExtension(technicalName, type) {
        return this.httpClient
            .put(`_action/${this.getApiBasePath()}/activate/${type}/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3,
            });
    }

    deactivateExtension(technicalName, type) {
        return this.httpClient
            .put(`_action/${this.getApiBasePath()}/deactivate/${type}/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3,
            });
    }

    uninstallExtension(technicalName, type, removeData) {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/uninstall/${type}/${technicalName}`, { keepUserData: !removeData }, {
                headers: this.basicHeaders(),
                version: 3,
            });
    }

    removeExtension(technicalName, type) {
        return this.httpClient
            .delete(`_action/${this.getApiBasePath()}/remove/${type}/${technicalName}`, {
                headers: this.basicHeaders(),
                version: 3,
            });
    }

    cancelLicense(licenseId) {
        return this.httpClient
            .delete(`/license/cancel/${licenseId}`, {
                headers: this.basicHeaders(),
                version: 3,
            })
            .then(({ data }) => {
                return data;
            });
    }

    rateExtension({ authorName, extensionId, headline, rating, text, tocAccepted, version }) {
        return this.httpClient.post(
            `/license/rate/${extensionId}`,
            { authorName, headline, rating, text, tocAccepted, version },
            {
                headers: this.basicHeaders(),
                version: 3,
            },
        );
    }

    basicHeaders(context = null) {
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
        };

        if (context?.languageId) {
            headers['sw-language-id'] = context.languageId;
        }

        return headers;
    }

    async getMyExtensions() {
        const headers = this.getBasicHeaders();

        const { data } = await this.httpClient.get(`/_action/${this.getApiBasePath()}/installed`, {
            headers,
            version: 3,
        });

        return data;
    }

    upload(formData) {
        const additionalHeaders = { 'Content-Type': 'application/zip' };
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(
            `/_action/${this.getApiBasePath()}/upload`,
            formData,
            { headers },
        )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    refresh() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/refresh`, {}, { params: { }, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}
