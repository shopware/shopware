/**
 * @sw-package framework
 *
 * What the jsdom of the specs lacks for the compare page, and every browser has: `TextDecoder`, and a response.
 */
import { TextDecoder } from 'node:util';
import type { HttpResponse } from '../http';

Object.assign(globalThis, { TextDecoder });

const NO_HEADERS: Record<string, string> = Object.freeze({});

/**
 * @private
 *
 * A response with the parts the page reads. An object body is sent as JSON.
 */
export function respond(body: unknown, status = 200, headers = NO_HEADERS): HttpResponse {
    const text = typeof body === 'string' ? body : JSON.stringify(body);
    const bytes = Buffer.from(text, 'utf8');

    return {
        ok: status < 400,
        status,
        headers: { get: (name: string) => headers[name] ?? null },
        json: () => Promise.resolve(JSON.parse(text) as unknown),
        text: () => Promise.resolve(text),
        arrayBuffer: () => Promise.resolve(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength)),
    };
}
