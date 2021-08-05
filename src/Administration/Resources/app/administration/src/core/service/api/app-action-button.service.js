import ApiService from '../api.service';

export class InvalidActionButtonParameterError extends Error {
    constructor(message) {
        super(message);
        this.name = 'InvalidActionButtonParameterError';
    }
}

export default class AppActionButtonService extends ApiService {
    /**
     * @param {AxiosInstance} httpClient
     * @param {LoginService} loginService
     */
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'appActionButtonService';
    }

    getBasicHeaders() {
        return {
            ...super.getBasicHeaders(),
            'sw-language-id': Shopware.Context.api.languageId,
        };
    }

    /**
     * Fetches available actions for a page
     *
     * @param {string} entity
     * @param {string} view
     */
    getActionButtonsPerView(entity, view) {
        if (!entity) {
            throw new InvalidActionButtonParameterError(`Parameter "entity" must have a valid value. Given: ${entity}`);
        }

        if (!view) {
            throw new InvalidActionButtonParameterError(`Parameter "view" must have a valid value. Given: ${view}`);
        }

        return this.httpClient
            .get(`app-system/action-button/${entity}/${view}`,
                {
                    headers: this.getBasicHeaders(),
                }).then(({ data }) => {
                return data.actions;
            });
    }

    /**
     * Run an action on the server
     *
     * @param {string} id
     * @param {Object} params
     */
    runAction(id, params = {}) {
        return this.httpClient
            .post(
                `app-system/action-button/run/${id}`,
                params,
                {
                    headers: this.getBasicHeaders(),
                },
            );
    }
}
