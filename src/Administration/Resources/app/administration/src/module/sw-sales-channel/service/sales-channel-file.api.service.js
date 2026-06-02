/**
 * @sw-package discovery
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

    saveConfiguration(file, salesChannelId, enabled, templateOverrides = file.configuration?.templateOverrides ?? {}) {
        if (file.configuration?.id) {
            return this.httpClient
                .patch(
                    `/sales-channel-file/${file.configuration.id}`,
                    {
                        enabled,
                        templateOverrides,
                    },
                    {
                        headers: this.getBasicHeaders(),
                    },
                )
                .then(() => {
                    return {
                        ...file.configuration,
                        enabled,
                        templateOverrides,
                    };
                });
        }

        const configuration = {
            id: Shopware.Utils.createId(),
            salesChannelId,
            fileFamily: file.fileFamily,
            fileName: file.fileName,
            enabled,
            templateOverrides,
        };

        return this.httpClient
            .post('/sales-channel-file', configuration, {
                headers: this.getBasicHeaders(),
            })
            .then(() => configuration);
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
