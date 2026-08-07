/**
 * @sw-package discovery
 *
 * @private
 */

const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class SalesChannelFileApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sales-channel-file') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'salesChannelFileApiService';
    }

    list(fileFamily, salesChannelId) {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/${fileFamily}/${salesChannelId}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    detail(fileFamily, salesChannelId, fileName) {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/${fileFamily}/${salesChannelId}/detail`, {
                headers: this.getBasicHeaders(),
                params: {
                    fileName,
                },
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    preview(fileFamily, salesChannelId, fileName, templateOverrides = {}) {
        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}/${fileFamily}/${salesChannelId}/preview`,
                {
                    fileName,
                    templateOverrides,
                },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Application.addServiceProvider('salesChannelFileApiService', () => {
    return new SalesChannelFileApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SalesChannelFileApiService;
