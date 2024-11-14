/**
 * @package services-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class CaptchaService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'captchaService';
    }

    list(cb) {
        const headers = this.getAuthHeaders();

        this.httpClient.get('/_action/captcha_list', { headers }).then((response) => cb(response.data));
    }

    getAuthHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
