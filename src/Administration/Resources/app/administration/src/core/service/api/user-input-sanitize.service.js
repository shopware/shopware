import ApiService from '../api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class AppUrlChangeService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'userInputSanitizeService';
    }

    /**
     *
     * @param {{ html: String, [field: String] }} param0
     * @returns {*} - ApiService.handleResponse(response)
     */
    sanitizeInput({ html, field }) {
        return this.httpClient
            .post(
                '_admin/sanitize-html',
                {
                    html,
                    field: field ?? null,
                },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => ApiService.handleResponse(response));
    }
}
