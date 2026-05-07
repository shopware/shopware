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

    describe('previewMailTemplate', () => {
        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            await mailApiService.previewMailTemplate(
                'mail-template-id',
                { order: 'order-id', salesChannel: 'sales-channel-id' },
                { a11yDocuments: [] },
                'sales-channel-id',
                true,
                true,
                { languageId: 'language-id' },
            );

            expect(clientMock.history.post[0].url).toBe(`/_action/mail-template/preview`);
            expect(clientMock.history.post[0].headers['sw-language-id']).toBe('language-id');
            expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
                mailTemplateId: 'mail-template-id',
                entities: { order: 'order-id', salesChannel: 'sales-channel-id' },
                templateData: { a11yDocuments: [] },
                salesChannelId: 'sales-channel-id',
                includeHeaderFooter: true,
                strictRendering: true,
            });
        });
    });

    describe('getDataAndSendMailTemplate', () => {
        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            await mailApiService.getDataAndSendMailTemplate(
                {
                    recipients: { 'test@example.com': 'Test User' },
                    mailTemplateId: 'mail-template-id',
                    entities: { order: 'order-id', salesChannel: 'sales-channel-id' },
                    templateData: { a11yDocuments: [] },
                },
                { languageId: 'language-id' },
            );

            expect(clientMock.history.post[0].url).toBe(`/_action/mail-template/get-data-and-send`);
            expect(clientMock.history.post[0].headers['sw-language-id']).toBe('language-id');
        });
    });

    describe('simulateMailTemplate', () => {
        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            await mailApiService.simulateMailTemplate(
                { contentHtml: '<p>Test</p>' },
                'checkout.order.placed',
                'sales-channel-id',
                true,
            );

            expect(clientMock.history.post[0].url).toBe(`/_action/mail-template/simulate`);
            expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
                templateParts: { contentHtml: '<p>Test</p>' },
                eventName: 'checkout.order.placed',
                strictRendering: true,
                salesChannelId: 'sales-channel-id',
            });
        });
    });
});
