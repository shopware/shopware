import ApiService from '../api.service';

export default class AppModulesService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'appModulesService';
    }

    fetchAppModules() {
        return this.httpClient.get(
            'app-system/modules',
            {
                headers: this.getBasicHeaders(),
            },
        ).then(({ data }) => {
            return data.modules;
        });
    }
}
