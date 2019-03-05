import ApiService from '../api.service';

/**
 * Gateway for the API end point "mail"
 * @class
 * @extends ApiService
 */
class MailApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mail-template') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mailService';
    }

    testMailTemplateById(recipient, mailTemplate, salesChannelId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/send`;

        return this.httpClient.post(
            apiRoute,
            { mailTemplate, recipient, salesChannelId },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default MailApiService;
