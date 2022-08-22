import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

type AppModuleDefinition = {
    name: string,
    label: { [key: string]: string },
    mainModule?: {
        source: string,
    },
    modules: Array<{
        name: string,
        label: { [key: string]: string },
        position: number,
        source?: string,
        parent?: string,
    }>
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class AppModulesService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, '', 'application/json');
        this.name = 'appModulesService';
    }

    public async fetchAppModules(): Promise<AppModuleDefinition[]> {
        const { data } = await this.httpClient.get<{ modules: AppModuleDefinition[] }>(
            'app-system/modules',
            {
                headers: this.getBasicHeaders(),
            },
        );

        return data.modules;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { AppModulesService, AppModuleDefinition };
