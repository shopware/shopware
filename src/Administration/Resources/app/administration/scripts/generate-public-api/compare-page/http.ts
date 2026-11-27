/**
 * @sw-package framework
 *
 * The parts of `fetch` that the compare page uses. `window.fetch` fits them, and so does a stand-in of a spec.
 */

/** @private */
export interface HttpResponse {
    ok: boolean;
    status: number;
    headers: { get(name: string): string | null };
    json(): Promise<unknown>;
    text(): Promise<string>;
    arrayBuffer(): Promise<ArrayBuffer>;
}

/** @private */
export type HttpFetch = (url: string, init?: { headers: Record<string, string> }) => Promise<HttpResponse>;

/**
 * @private
 *
 * The body of a response as git reads the file: `response.text()` drops a byte order mark, and git keeps it.
 */
export async function textOf(response: Pick<HttpResponse, 'arrayBuffer'>): Promise<string> {
    return new TextDecoder('utf-8', { ignoreBOM: true }).decode(await response.arrayBuffer());
}
