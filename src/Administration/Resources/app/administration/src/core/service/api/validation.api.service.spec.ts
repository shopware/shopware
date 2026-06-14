/**
 * @sw-package fundamentals@framework
 */
import MockAdapter from 'axios-mock-adapter';
import type { AxiosInstance } from 'axios';
import createHTTPClient from '../../factory/http.factory';
import createLoginService from '../login.service';
import ValidationApiService from './validation.api.service';

function createValidationApiService() {
    const context = Shopware.Context?.api || {};
    const client = createHTTPClient(context) as AxiosInstance;
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);

    const validationApiService = new ValidationApiService(client, loginService);

    return {
        validationApiService,
        clientMock,
    };
}

describe('core/service/api/validation.api.service.ts', () => {
    describe('validateEmailAddress', () => {
        it('should return true', async () => {
            const { validationApiService, clientMock } = createValidationApiService();

            clientMock.onPost('/_action/validation/email').reply(204, {});

            const result = await validationApiService.validateEmailAddress('anyValid@email.com');

            expect(result).toBe(true);
        });

        it('should return false because email is invalid', async () => {
            const { validationApiService, clientMock } = createValidationApiService();

            clientMock.onPost('/_action/validation/email').reply(422, {});

            const result = await validationApiService.validateEmailAddress('invalid@email');

            expect(result).toBe(false);
        });

        it('should return false because exception occurred', async () => {
            const { validationApiService, clientMock } = createValidationApiService();

            clientMock.onPost('/_action/validation/email').reply(() => {
                throw Error('an error occurred');
            });

            const result = await validationApiService.validateEmailAddress('invalid@email');

            expect(result).toBe(false);
        });
    });
});
