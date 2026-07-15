import { appUrl, unresolvedPlaceholders } from '../../bundle.mjs';
import { HttpRequestBlocked } from './request-blocked.mjs';

/**
 * Sends an ordered HTTP scenario and records the final response for assertion classification.
 *
 * Earlier requests are setup steps: they may create state and pass forward a Store API context token,
 * but a non-2xx setup response blocks the leg before the final symptom assertion is judged.
 */
export class HttpRequestSequenceRunner {
  constructor(requestPreparer) {
    this.requestPreparer = requestPreparer;
  }

  /**
   * Runs all requests, preserving the final response and a human-readable curl preview.
   *
   * Blocked request states are returned as data so the executor can still include partial evidence
   * instead of throwing away the generated preview.
   */
  async send(requests, ids) {
    const evaluation = { code: '', bodyText: '', blocked: '', fakeScript: '' };

    try {
      for (let i = 0; i < requests.length; i += 1) {
        const request = await this.requestPreparer.prepare(requests[i], ids);

        this.ensureResolvedRequest(request, i);
        evaluation.fakeScript += this.renderCurl(request);

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
  ensureResolvedRequest({ method, path, body, headers }, index) {
    const leftover = unresolvedPlaceholders([path, body, ...Object.values(headers)].join('\n'));
    if (leftover.length === 0) {
      return;
    }

    throw new HttpRequestBlocked(`request ${index + 1} (${method} ${path}) has unresolved placeholder(s) ${leftover.join(', ')} -- the referenced context was not resolved`);
  }

  /**
   * Renders an approximate reproduction script without harness-owned credentials.
   */
  renderCurl({ method, path, body, authoredHeaders }) {
    const shownHeaders = Object.entries(authoredHeaders)
      .filter(([key]) => !this.requestPreparer.isExecutorOwnedHeader(key))
      .map(([key, value]) => ` -H "${key}: ${value}"`).join('');

    return `curl -sS -X ${method} "$APP_URL${path}"${shownHeaders}${body ? ` --data '${body}'` : ''}\n`;
  }

  async fetchRequest({ method, path, body, headers }, index) {
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
  captureContextToken(response, ids) {
    const contextToken = response.headers.get('sw-context-token');
    if (contextToken) {
      ids.SW_CONTEXT_TOKEN = contextToken;
    }
  }

  ensureSetupRequestSucceeded({ method, path }, index, requestCount, evaluation) {
    if (index >= requestCount - 1 || evaluation.code.startsWith('2')) {
      return;
    }

    throw new HttpRequestBlocked(`setup request ${index + 1} (${method} ${path}) returned HTTP ${evaluation.code} -- ${evaluation.bodyText.slice(0, 300)}`);
  }
}
