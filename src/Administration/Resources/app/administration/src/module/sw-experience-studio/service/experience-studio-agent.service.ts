import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from 'src/core/service/api.service';

export type ExperienceStudioAgentMessage = {
    role: 'assistant' | 'user';
    content: string;
};

/**
 * @private
 */
export default class ExperienceStudioAgentService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'experience-studio-agent') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'experienceStudioAgentService';
    }

    send(payload: {
        prompt: string;
        messages: ExperienceStudioAgentMessage[];
        layout: unknown[];
        rootSource: string | null;
        selectedElementId: string | null;
        elementTypes: unknown[];
        styleOptions: Record<string, unknown>;
    }): Promise<{ message: string; layout?: unknown[] }> {
        return this.httpClient
            .post<{ message: string; layout?: unknown[] }>('/_action/experience-studio-agent/turn', payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<{ message: string; layout?: unknown[] }>(response));
    }
}
