/**
 * @package services-settings
 */
import CaptchaService from 'src/module/sw-settings-basic-information/service/captcha.service';

describe('src/module/sw-settings-basic-information/service/captcha.service.js', () => {
    const response = [
        'technical',
        'names',
    ];
    const token = 'fce2c5c0-518c-4f16-b893-4f0913c07efe';

    let captchaService = null;

    beforeEach(async () => {
        const httpClient = {
            get: jest.fn(() =>
                Promise.resolve({
                    data: response,
                }),
            ),
        };

        const loginService = {
            getToken: jest.fn(() => token),
        };

        captchaService = new CaptchaService(httpClient, loginService);
    });

    it('should be initialized', async () => {
        expect(captchaService).not.toBeNull();
        expect(captchaService).toBeInstanceOf(CaptchaService);
    });

    it('should return auth headers', async () => {
        expect(captchaService.getAuthHeaders()).toMatchObject({
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        });
        expect(captchaService.loginService.getToken).toHaveBeenCalledTimes(1);
    });

    it('should provide the list callback with data', async () => {
        const spyCallback = jest.fn();

        await captchaService.list(spyCallback);

        expect(spyCallback).toHaveBeenCalledWith(response);
    });
});
