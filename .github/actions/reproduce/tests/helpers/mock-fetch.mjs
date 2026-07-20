// Reusable global-fetch stub for testing the network modules (admin-api, executor runners) without a
// live shop. `route(url, init)` returns { status?, ok?, json?, text? } per request; `calls` records
// every request so a test can assert how many times / with what body an endpoint was hit.
export function installMockFetch(route) {
  const original = globalThis.fetch;
  const calls = [];
  globalThis.fetch = async (url, init = {}) => {
    calls.push({ url: String(url), init, method: init.method || 'GET' });
    const r = route(String(url), init) || {};
    const status = r.status ?? 200;
    return {
      ok: r.ok ?? (status >= 200 && status < 300),
      status,
      json: async () => (typeof r.json === 'function' ? r.json(calls.length) : (r.json ?? {})),
      text: async () => r.text ?? '',
      headers: { get: (name) => (r.headers ? r.headers[name.toLowerCase()] ?? null : null) },
    };
  };
  return { calls, restore: () => { globalThis.fetch = original; } };
}
