import { AxiosInstance, AxiosResponse } from 'axios';
import ApiService from '../api.service';
import { LoginService } from '../login.service';

export type action = {
    url: string,
    entity: string,
    action: string,
    appName: string,
};

export default class ExtensionSdkService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, 'extension-sdk', 'application/json');

        this.name = 'extensionSdkService';
    }

    /**
     * @internal Only to be used by the extension sdk
     */
    runAction(action: action, entityIdList: string[]): Promise<unknown> {
        return this.httpClient.post(
            `/_action/${this.getApiBasePath()}/run-action`,
            {
                ...action,
                ids: entityIdList,
            },
            {
                params: {},
                headers: this.getBasicHeaders(),
            },
        ).then((response: AxiosResponse<unknown>) => {
            ApiService.handleResponse(response);
        });
    }
}
