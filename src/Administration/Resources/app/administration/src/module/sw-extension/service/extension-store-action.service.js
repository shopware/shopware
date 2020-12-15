import ApiService from 'src/core/service/api.service';

export default class ExtensionStoreActionService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'plugin') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'extensionStoreActionService';
    }

    downloadExtension(technicalName) {
        return this.httpClient
            .post(`_action/extension/download/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3
            });
    }

    installExtension(technicalName, type) {
        return this.httpClient
            .post(`_action/extension/install/${type}/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3
            });
    }

    updateExtension(technicalName, type) {
        return this.httpClient
            .post(`_action/extension/update/${type}/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3
            });
    }

    activateExtension(technicalName, type) {
        return this.httpClient
            .put(`_action/extension/activate/${type}/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3
            });
    }

    deactivateExtension(technicalName, type) {
        return this.httpClient
            .put(`_action/extension/deactivate/${type}/${technicalName}`, {}, {
                headers: this.basicHeaders(),
                version: 3
            });
    }

    uninstallExtension(technicalName, type, removeData) {
        return this.httpClient
            .post(`_action/extension/uninstall/${type}/${technicalName}`, { keepUserData: !removeData }, {
                headers: this.basicHeaders(),
                version: 3
            });
    }

    removeExtension(technicalName, type) {
        return this.httpClient
            .delete(`_action/extension/remove/${type}/${technicalName}`, {
                headers: this.basicHeaders(),
                version: 3
            });
    }

    cancelAndRemoveExtension(licenseId) {
        return this.httpClient
            .delete(`/license/cancel/${licenseId}`, {
                headers: this.basicHeaders(),
                version: 3
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
