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

type InlineMappingParseResult =
    | ContentSystemInlineMappingParseResponse
    | { revision: number; error: { code: string; message: string } };

interface PendingInlineMappingParse {
    text: string;
    revision: number;
    resolve: (response: ContentSystemInlineMappingParseResponse) => void;
    reject: (error: unknown) => void;
}

const INLINE_MAPPING_PARSE_BATCH_SIZE = 50;

/**
 * Gateway for the content system mapping candidate catalogue, keyed by root source.
 */
class ContentSystemMappingCandidateApiService extends ApiService {
    private pendingInlineMappingParses: PendingInlineMappingParse[] = [];

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

            if (this.pendingInlineMappingParses.length === 1) {
                void Promise.resolve().then(() => this.flushInlineMappingParses());
            }
        });
    }

    private flushInlineMappingParses(): void {
        const pending = this.pendingInlineMappingParses;
        this.pendingInlineMappingParses = [];

        for (let offset = 0; offset < pending.length; offset += INLINE_MAPPING_PARSE_BATCH_SIZE) {
            void this.parseInlineMappingBatch(pending.slice(offset, offset + INLINE_MAPPING_PARSE_BATCH_SIZE));
        }
    }

    private async parseInlineMappingBatch(pending: PendingInlineMappingParse[]): Promise<void> {
        try {
            const response = await this.httpClient.post<{ results: InlineMappingParseResult[] }>(
                '/_action/content-system/inline-mapping/parse-batch',
                { requests: pending.map(({ text, revision }) => ({ text, revision })) },
                { headers: this.getBasicHeaders() },
            );
            const payload = ApiService.handleResponse<{ results: InlineMappingParseResult[] }>(response);

            if (!Array.isArray(payload?.results) || payload.results.length !== pending.length) {
                throw new Error('Invalid inline mapping parse batch response.');
            }

            payload.results.forEach((result, index) => {
                if ('error' in result) {
                    pending[index].reject(new Error(result.error.message));
                } else {
                    pending[index].resolve(result);
                }
            });
        } catch (error) {
            pending.forEach((request) => request.reject(error));
        }
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemMappingCandidateApiService;
