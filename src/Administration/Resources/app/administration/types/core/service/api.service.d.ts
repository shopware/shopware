import { AxiosInstance, AxiosResponse } from 'axios';
import { LoginService } from './login.service';

export class ApiService {
    constructor(
        httpClient: AxiosInstance,
        loginService: LoginService,
        apiEndpoint: string,
        contextType?: string
    );

    getApiBasePath(id: string | number, prefix?: string): string;

    getBasicHeaders(additionalHeaders?: any): any;

    static handleResponse(response: AxiosResponse): any;

    static parseJsonApiData(data: any): any;

    static getVersionHeader(versionId: string): { 'sw-version-id': string };

    get apiEndpoint(): string;

    set apiEndpoint(endpoint: string);

    get httpClient(): AxiosInstance;

    set httpClient(client: AxiosInstance);

    get contentType(): string;

    set contentType(contentType: string);
}
