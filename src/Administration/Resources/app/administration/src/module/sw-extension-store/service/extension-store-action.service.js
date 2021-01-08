import ApiService from 'src/core/service/api.service';

export default class ExtensionStoreActionService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'plugin') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'extensionStoreActionService';
    }

    downloadExtension(technicalName) {
        return this.httpClient
            .post(`/_action/extension/download/${technicalName}`, {}, { headers: this.basicHeaders() });
    }

    installExtension(technicalName, type) {
        return this.httpClient
            .post(`/_action/extension/install/${type}/${technicalName}`, {}, { headers: this.basicHeaders() });
    }

    updateExtension(technicalName, type) {
        return this.httpClient
            .post(`/_action/extension/update/${type}/${technicalName}`, {}, { headers: this.basicHeaders() });
    }

    activateExtension(technicalName, type) {
        return this.httpClient
            .put(`/_action/extension/activate/${type}/${technicalName}`, {}, { headers: this.basicHeaders() });
    }

    deactivateExtension(technicalName, type) {
        return this.httpClient
            .put(`/_action/extension/deactivate/${type}/${technicalName}`, {}, { headers: this.basicHeaders() });
    }

    uninstallExtension(technicalName, type, removeData) {
        return this.httpClient
            .post(`/_action/extension/uninstall/${type}/${technicalName}`, { keepUserData: !removeData }, { headers: this.basicHeaders() });
    }

    removeExtension(technicalName, type) {
        return this.httpClient
            .delete(`/_action/extension/remove/${type}/${technicalName}`, { headers: this.basicHeaders() });
    }

    cancelAndRemoveExtension(licenseId) {
        return this.httpClient
            .delete(`/_action/license/cancel/${licenseId}`, { headers: this.basicHeaders() })
            .then(({ data }) => {
                return data;
            });
    }

    rateExtension({ authorName, extensionId, headline, rating, text, tocAccepted, version }) {
        return this.httpClient.post(
            `/_action/license/rate/${extensionId}`,
            { authorName, headline, rating, text, tocAccepted, version },
            { headers: this.basicHeaders() }
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
