/**
 * @package checkout
 */
import MailApiService from 'src/core/service/api/mail.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function getMailApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const mailApiService = new MailApiService(client, loginService);

    clientMock.onAny().reply(200, {
        data: null,
    });

    return { mailApiService, clientMock };
}

describe('mailApiService', () => {
    it('is registered correctly', async () => {
        const { mailApiService } = getMailApiService();
        expect(mailApiService).toBeInstanceOf(MailApiService);
    });

    it('has the correct name', async () => {
        const { mailApiService } = getMailApiService();

        expect(mailApiService.name).toBe('mailService');
    });

    describe('sendMailTemplate', () => {
        it('is defined', async () => {
            const { mailApiService } = getMailApiService();

            expect(mailApiService.sendMailTemplate).toBeDefined();
        });

        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            const recipientMail = 'test@example.com';
            const recipient = { name: 'Test User' };
            const mailTemplate = {
                contentHtml: '<p>Test</p>',
                contentPlain: 'Test',
                subject: 'Test Subject',
                senderMail: 'sender@example.com',
                senderName: 'Sender',
            };
            const templateData = { test: 'data' };
            const mailTemplateMedia = { getIds: jest.fn().mockReturnValue(['media-id']) };
            const salesChannelId = 'sales-channel-id';

            await mailApiService.sendMailTemplate(
                recipientMail,
                recipient,
                mailTemplate,
                mailTemplateMedia,
                salesChannelId,
                false,
                [],
                templateData,
                null,
                null,
                { languageId: 'language-id' },
            );

            expect(clientMock.history.post[0].url).toBe(`/_action/mail-template/send`);
            expect(clientMock.history.post[0].headers['sw-language-id']).toBe('language-id');
        });
    });

    describe('buildRenderPreview', () => {
        it('is defined', async () => {
            const { mailApiService } = getMailApiService();

            expect(mailApiService.buildRenderPreview).toBeDefined();
        });

        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            const mailTemplate = {
                contentHtml: '<p>Test</p>',
                contentPlain: 'Test',
                subject: 'Test Subject',
                senderMail: 'sender@example.com',
                senderName: 'Sender',
            };

            await mailApiService.buildRenderPreview('invoice', mailTemplate);

            expect(clientMock.history.post[0].url).toBe(`/_action/mail-template/build`);
            expect(clientMock.history.post[0].headers['sw-language-id']).toBe(Shopware.Context.api.languageId);
        });
    });
});
