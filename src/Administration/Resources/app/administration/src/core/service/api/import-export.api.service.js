const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "import-export"
 * @class
 * @extends ApiService
 */
class ImportExportApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'import-export') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'importExportService';
    }

    getFeatures() {
        const apiRoute = `/_action/${this.getApiBasePath()}/features`;

        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    initiate(profileId, expireDate, file) {
        const apiRoute = `/_action/${this.getApiBasePath()}/initiate`;
        const formData = new FormData();
        if (file) {
            formData.append('file', file);
        }
        formData.append('profileId', profileId);
        formData.append('expireDate', expireDate);

        return this.httpClient.post(
            apiRoute,
            formData,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    process(logId, offset) {
        const apiRoute = `/_action/${this.getApiBasePath()}/process`;

        return this.httpClient.post(
            apiRoute,
            {
                logId: logId,
                offset: offset
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    cancel(logId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/cancel`;

        return this.httpClient.post(
            apiRoute,
            {
                logId: logId
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    getDownloadUrl(fileId, accessToken) {
        const baseUrl = `${Shopware.Context.api.apiPath}/v${this.getApiVersion()}`;
        return `/${baseUrl}/_action/${this.getApiBasePath()}/file/download?fileId=${fileId}&accessToken=${accessToken}`;
    }
}

export default ImportExportApiService;
