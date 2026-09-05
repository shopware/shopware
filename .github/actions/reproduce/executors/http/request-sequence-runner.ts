import { appUrl, unresolvedPlaceholders } from '../../bundle.ts';
import { HttpRequestBlocked } from './request-blocked.ts';
import type { HttpRequestPreparer, PreparedRequest } from './request-preparer.ts';
import type { HttpRequest } from '../../types.ts';

/**
 * The recorded outcome of an HTTP scenario: the final response, an optional blocker, and a curl preview.
 */
export interface HttpEvaluation {
  code: string;
  bodyText: string;
  blocked: string;
  fakeScript: string;
}

/**
 * Sends an ordered HTTP scenario and records the final response for assertion classification.
 *
 * Earlier requests are setup steps: they may create state and pass forward a Store API context token,
 * but a non-2xx setup response blocks the leg before the final symptom assertion is judged.
 */
export class HttpRequestSequenceRunner {
  requestPreparer: HttpRequestPreparer;

  constructor(requestPreparer: HttpRequestPreparer) {
    this.requestPreparer = requestPreparer;
  }

  /**
   * Runs all requests, preserving the final response and a human-readable curl preview.
   *
   * Blocked request states are returned as data so the executor can still include partial evidence
   * instead of throwing away the generated preview.
   */
  async send(requests: HttpRequest[], ids: Record<string, string>): Promise<HttpEvaluation> {
    const evaluation: HttpEvaluation = { code: '', bodyText: '', blocked: '', fakeScript: '' };

    try {
      for (let i = 0; i < requests.length; i += 1) {
        const request = await this.requestPreparer.prepare(requests[i], ids);

        this.ensureResolvedRequest(request, i);
        evaluation.fakeScript += this.renderCurl(request, i === requests.length - 1, requests.length);

        const response = await this.fetchRequest(request, i);
        evaluation.code = String(response.status);
        evaluation.bodyText = await response.text();

        this.captureContextToken(response, ids);
        this.ensureSetupRequestSucceeded(request, i, requests.length, evaluation);
      }

      return evaluation;
    } catch (error) {
      if (error instanceof HttpRequestBlocked) {
        return { ...evaluation, blocked: error.reason };
      }

      throw error;
    }
  }

  /**
   * Rejects unresolved placeholders before a request reaches the live shop.
   */
  ensureResolvedRequest({ method, path, body, headers }: PreparedRequest, index: number): void {
    const leftover = unresolvedPlaceholders([path, body, ...Object.values(headers)].join('\n'));
    if (leftover.length === 0) {
      return;
    }

    throw new HttpRequestBlocked(`request ${index + 1} (${method} ${path}) has unresolved placeholder(s) ${leftover.join(', ')} -- the referenced context was not resolved`);
  }

  /**
   * Renders an approximate reproduction script without harness-owned credentials.
   */
  renderCurl({ method, path, body, authoredHeaders }: PreparedRequest, isLast: boolean, total: number): string {
    const shownHeaders = Object.entries(authoredHeaders)
      .filter(([key]) => !this.requestPreparer.isExecutorOwnedHeader(key))
      .map(([key, value]) => ` -H "${key}: ${value}"`).join('');

    // With a setup sequence, annotate each request's role so a reader knows the checks bind ONLY to
    // the final (symptom) request — earlier requests are setup that just has to return 2xx. A single
    // request needs no label (it is obviously the asserted one).
    const label = total > 1
      ? `${isLast ? '# symptom — the checks below run on this response' : '# setup'}\n`
      : '';

    return `${label}curl -sS -X ${method} "$APP_URL${path}"${shownHeaders}${body ? ` --data '${body}'` : ''}\n`;
  }

  async fetchRequest({ method, path, body, headers }: PreparedRequest, index: number): Promise<Response> {
    // Resolve the authored path against the shop base and REQUIRE it to stay same-origin. The http leg
    // runs host-side with no container/egress DROP, so string-concatenation (`${appUrl()}${path}`)
    // would let a path like `@evil.com/x` or `//evil.com` (userinfo / protocol-relative) send the
    // fetch off-origin. new URL(path, base) resolves a bare path as a path segment (safe), and the
    // origin assert rejects absolute/protocol-relative/userinfo escapes.
    let target;
    try {
      target = new URL(path, `${appUrl()}/`);
    } catch {
      throw new HttpRequestBlocked(`request ${index + 1} (${method} ${path}) -- malformed path`);
    }
    if (target.origin !== new URL(`${appUrl()}/`).origin) {
      throw new HttpRequestBlocked(`request ${index + 1} (${method} ${path}) -- refusing off-origin request to ${target.origin}; the path must be same-origin as the shop`);
    }
    try {
      return await fetch(target, { method, headers, body: body || undefined });
    } catch {
      throw new HttpRequestBlocked(`request ${index + 1} (${method} ${path}) -- transport failure`);
    }
  }

  /**
   * Carries Store API session state from one request to the next when Shopware rotates the token.
   */
  captureContextToken(response: Response, ids: Record<string, string>): void {
    const contextToken = response.headers.get('sw-context-token');
    if (contextToken) {
      ids.SW_CONTEXT_TOKEN = contextToken;
    }
  }

  ensureSetupRequestSucceeded({ method, path }: PreparedRequest, index: number, requestCount: number, evaluation: HttpEvaluation): void {
    if (index >= requestCount - 1 || evaluation.code.startsWith('2')) {
      return;
    }

    throw new HttpRequestBlocked(`setup request ${index + 1} (${method} ${path}) returned HTTP ${evaluation.code} -- ${evaluation.bodyText.slice(0, 300)}`);
  }
}
