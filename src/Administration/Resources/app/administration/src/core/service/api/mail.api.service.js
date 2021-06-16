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

    testMailTemplate(recipient, mailTemplate, mailTemplateMedia, salesChannelId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/send`;

        return this.httpClient.post(
            apiRoute,
            {
                contentHtml: mailTemplate.contentHtml ?? mailTemplate.translated?.contentHtml,
                contentPlain: mailTemplate.contentPlain ?? mailTemplate.translated?.contentPlain,
                recipients: { [recipient]: recipient },
                salesChannelId: salesChannelId,
                mediaIds: mailTemplateMedia.getIds(),
                subject: mailTemplate.subject ?? mailTemplate.translated?.subject,
                senderMail: mailTemplate.senderMail,
                senderName: mailTemplate.senderName ?? mailTemplate.translated?.senderName,
                testMode: true,
            },
            {
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    buildRenderPreview(mailTemplateType, mailTemplate) {
        const apiRoute = `/_action/${this.getApiBasePath()}/build`;

        return this.httpClient.post(
            apiRoute,
            {
                mailTemplateType: mailTemplateType,
                mailTemplate: mailTemplate,
            },
            {
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default MailApiService;
