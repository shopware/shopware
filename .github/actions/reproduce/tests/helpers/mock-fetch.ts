// Reusable global-fetch stub for testing the network modules (admin-api, executor runners) without a
// live shop. `route(url, init)` returns { status?, ok?, json?, text? } per request; `calls` records
// every request so a test can assert how many times / with what body an endpoint was hit.

// The JSON body a route can return: either a value or a factory that receives the 1-based call count.
type RouteJson = ((count: number) => unknown) | Record<string, unknown> | unknown[];

// What a route callback may return per request; every field is optional so `{}` is a valid response.
export interface RouteResult {
  status?: number;
  ok?: boolean;
  json?: RouteJson;
  text?: string;
  headers?: Record<string, string>;
}

// A route callback maps a request (url + init) to the canned response fields above.
export type RouteFn = (url: string, init: RequestInit) => RouteResult | undefined | null;

// One recorded request, exposed via the returned `calls` array.
export interface MockCall {
  url: string;
  init: RequestInit;
  method: string;
}

export interface MockFetchHandle {
  calls: MockCall[];
  restore: () => void;
}

export function installMockFetch(route: RouteFn): MockFetchHandle {
  const original = globalThis.fetch;
  const calls: MockCall[] = [];
  globalThis.fetch = async (url, init = {}): Promise<Response> => {
    calls.push({ url: String(url), init, method: init.method || 'GET' });
    const r: RouteResult = route(String(url), init) || {};
    const status = r.status ?? 200;
    return {
      ok: r.ok ?? (status >= 200 && status < 300),
      status,
      json: async () => (typeof r.json === 'function' ? r.json(calls.length) : (r.json ?? {})),
      text: async () => r.text ?? '',
      headers: { get: (name: string) => (r.headers ? r.headers[name.toLowerCase()] ?? null : null) },
    } as unknown as Response;
  };
  return { calls, restore: () => { globalThis.fetch = original; } };
}
