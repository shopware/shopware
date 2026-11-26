/**
 * @sw-package framework
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * One entry of the curated catalogue of entity data an author may bind a mappable property to.
 *
 * `label` and `description` are snippet keys. Dynamic candidates may additionally carry literal translations
 * sourced from their own configuration.
 *
 * @private
 */
export interface ContentSystemMappingCandidate {
    path: string;
    source: { type: string; id: string; config?: Record<string, unknown>; path?: string };
    label: string;
    description: string;
    group: string;
    valueType: string;
    /** Declared property types that accept this candidate, computed by the server's mapping compatibility rule. */
    compatibleTypes: string[];
    /** Whether the server's inline mapping interpolator can safely render this candidate as text. */
    inlineMappable: boolean;
    /** Canonical server-generated token text, or null when the path is not valid inline token syntax. */
    canonicalToken: string | null;
    contextType: 'single' | 'collection';
    labelTranslations?: Record<string, string>;
    descriptionTranslations?: Record<string, string>;
}

/**
 * @private
 */
interface ContentSystemMappingCandidateResponse {
    mappingCandidates: Record<string, ContentSystemMappingCandidate[]>;
}

/**
 * One token span returned by the PHP inline mapping parser.
 *
 * @private
 */
export interface ContentSystemInlineMappingToken {
    path: string;
    match: string;
    /** Canonical server-generated token text, or null for a malformed token. */
    canonicalToken: string | null;
    offset: number;
    insideMarkup: boolean;
    malformed: boolean;
}

/**
 * @private
 */
export interface ContentSystemInlineMappingParseResponse {
    revision: number;
    tokens: ContentSystemInlineMappingToken[];
}

interface PendingInlineMappingParse {
    text: string;
    revision: number;
    resolve: (response: ContentSystemInlineMappingParseResponse) => void;
    reject: (error: unknown) => void;
}

const MAX_CONCURRENT_INLINE_MAPPING_PARSES = 3;

/**
 * Gateway for the content system mapping candidate catalogue, keyed by root source.
 */
class ContentSystemMappingCandidateApiService extends ApiService {
    private pendingInlineMappingParses: PendingInlineMappingParse[] = [];

    private activeInlineMappingParses = 0;

    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'content-system-mapping-candidates') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemMappingCandidateService';
    }

    getMappingCandidates(): Promise<Record<string, ContentSystemMappingCandidate[]>> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<ContentSystemMappingCandidateResponse>('/_info/content-system-mapping-candidates.json', {
                headers,
            })
            .then((response) => {
                const payload = ApiService.handleResponse<ContentSystemMappingCandidateResponse>(response);
                const candidates = payload?.mappingCandidates;

                if (
                    typeof candidates !== 'object' ||
                    candidates === null ||
                    Array.isArray(candidates) ||
                    Object.values(candidates).some((entries) => !Array.isArray(entries))
                ) {
                    throw new Error('Invalid mapping candidate catalogue response.');
                }

                return candidates;
            });
    }

    parseInlineMappingTokens(text: string, revision: number): Promise<ContentSystemInlineMappingParseResponse> {
        return new Promise((resolve, reject) => {
            this.pendingInlineMappingParses.push({ text, revision, resolve, reject });

            this.processInlineMappingQueue();
        });
    }

    private processInlineMappingQueue(): void {
        while (this.activeInlineMappingParses < MAX_CONCURRENT_INLINE_MAPPING_PARSES) {
            const request = this.pendingInlineMappingParses.shift();
            if (!request) {
                return;
            }

            this.activeInlineMappingParses += 1;
            void this.parseInlineMappingRequest(request);
        }
    }

    private async parseInlineMappingRequest(request: PendingInlineMappingParse): Promise<void> {
        try {
            const response = await this.httpClient.post<ContentSystemInlineMappingParseResponse>(
                '/_action/content-system/inline-mapping/parse',
                { text: request.text, revision: request.revision },
                { headers: this.getBasicHeaders() },
            );
            const payload = ApiService.handleResponse<ContentSystemInlineMappingParseResponse>(response);

            if (typeof payload?.revision !== 'number' || !Array.isArray(payload.tokens)) {
                throw new Error('Invalid inline mapping parse response.');
            }

            request.resolve(payload);
        } catch (error) {
            request.reject(error);
        } finally {
            this.activeInlineMappingParses -= 1;
            this.processInlineMappingQueue();
        }
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemMappingCandidateApiService;
