import { fillPlaceholders } from '../../bundle.ts';
import { token } from '../../admin-api.ts';
import type { HttpRequest } from '../../types.ts';

/**
 * The normalized request the harness is allowed to send, after placeholder filling and header policy.
 */
export interface PreparedRequest {
  method: string;
  path: string;
  body: string;
  headers: Record<string, string>;
  authoredHeaders: Record<string, string>;
}

/**
 * Normalizes one authored HTTP request into the request the harness is allowed to send.
 *
 * This class is the policy boundary for executor-owned credentials: authored headers may describe
 * the scenario, but auth, cookies, and context tokens remain controlled by the harness.
 */
export class HttpRequestPreparer {
  static EXECUTOR_OWNED_HEADERS = new Set(['authorization', 'sw-access-key', 'cookie', 'sw-context-token']);

  /**
   * Fills placeholders and combines executor-owned headers with safe authored headers.
   */
  async prepare(req: HttpRequest, ids: Record<string, string>): Promise<PreparedRequest> {
    const method = req.method || 'GET';
    const path = fillPlaceholders(req.path || '', ids);
    const body = fillPlaceholders(req.body, ids);
    const headers = await this.executorHeaders(path, ids);

    this.applyAuthoredHeaders(headers, req.headers || {}, ids);
    this.ensureContentType(headers, body);

    return { method, path, body, headers, authoredHeaders: req.headers || {} };
  }

  /**
   * Builds the harness-owned authentication and session headers for the target API family.
   */
  async executorHeaders(path: string, ids: Record<string, string>): Promise<Record<string, string>> {
    const headers: Record<string, string> = { Accept: 'application/json' };

    if (this.isAdminPath(path)) {
      headers.Authorization = `Bearer ${await token()}`;
    } else if (ids.SW_ACCESS_KEY) {
      headers['sw-access-key'] = ids.SW_ACCESS_KEY;
    }
    if (ids.SW_CONTEXT_TOKEN) {
      headers['sw-context-token'] = ids.SW_CONTEXT_TOKEN;
    }

    return headers;
  }

  /**
   * Copies agent-authored headers except those that would override harness auth or session state.
   */
  applyAuthoredHeaders(headers: Record<string, string>, authoredHeaders: Record<string, string>, ids: Record<string, string>): void {
    for (const [key, value] of Object.entries(authoredHeaders)) {
      if (this.isExecutorOwnedHeader(key)) {
        continue;
      }

      headers[key] = fillPlaceholders(value, ids);
    }
  }

  ensureContentType(headers: Record<string, string>, body: string): void {
    const hasContentType = Object.keys(headers).some((header) => header.toLowerCase() === 'content-type');
    if (body && !hasContentType) {
      headers['Content-Type'] = 'application/json';
    }
  }

  isAdminPath(path: string): boolean {
    return path.startsWith('/api/');
  }

  isStorePath(path: string): boolean {
    return path.startsWith('/store-api/');
  }

  isExecutorOwnedHeader(header: string): boolean {
    return HttpRequestPreparer.EXECUTOR_OWNED_HEADERS.has(header.toLowerCase());
  }
}
