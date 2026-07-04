/**
 * @sw-package framework
 */

import {
    base64UrlToBuffer,
    bufferToBase64Url,
    createCredential,
    getAssertion,
    isWebAuthnSupported,
} from 'src/core/helper/webauthn.helper';

function defineNavigatorCredentials(value: unknown): void {
    Object.defineProperty(window.navigator, 'credentials', {
        value,
        configurable: true,
    });
}

function definePublicKeyCredential(value: unknown): void {
    Object.defineProperty(window, 'PublicKeyCredential', {
        value,
        configurable: true,
        writable: true,
    });
}

function enableWebAuthn(credentials: { get?: jest.Mock; create?: jest.Mock }): void {
    definePublicKeyCredential(function PublicKeyCredential() {});
    defineNavigatorCredentials(credentials);
}

function disableWebAuthn(): void {
    definePublicKeyCredential(undefined);
    defineNavigatorCredentials(undefined);
}

interface CapturedRequestOptions {
    publicKey: {
        rpId?: string;
        challenge: ArrayBuffer;
        allowCredentials: { id: ArrayBuffer; type: string }[];
    };
}

interface CapturedCreationOptions {
    publicKey: {
        challenge: ArrayBuffer;
        user: { id: ArrayBuffer; name?: string };
        excludeCredentials: { id: ArrayBuffer; type: string }[];
    };
}

describe('core/helper/webauthn.helper.ts', () => {
    afterEach(() => {
        disableWebAuthn();
    });

    describe('base64url encoding', () => {
        it('should roundtrip base64url strings', () => {
            const original = 'SGVsbG8gV29ybGQ'; // "Hello World", unpadded base64url

            expect(bufferToBase64Url(base64UrlToBuffer(original))).toBe(original);
        });

        it('should decode base64url with url-safe characters and missing padding', () => {
            const bytes = new Uint8Array([
                251,
                239,
                190,
                255,
            ]);
            const encoded = bufferToBase64Url(bytes);

            expect(encoded).not.toContain('+');
            expect(encoded).not.toContain('/');
            expect(encoded).not.toContain('=');
            expect(new Uint8Array(base64UrlToBuffer(encoded))).toEqual(bytes);
        });

        it('should encode ArrayBuffer, Uint8Array and other views identically', () => {
            const bytes = new Uint8Array([
                1,
                2,
                3,
                4,
            ]);

            expect(bufferToBase64Url(bytes)).toBe(bufferToBase64Url(bytes.buffer));
            expect(bufferToBase64Url(new DataView(bytes.buffer))).toBe(bufferToBase64Url(bytes));
        });
    });

    describe('isWebAuthnSupported', () => {
        it('should return false when the browser has no WebAuthn API', () => {
            disableWebAuthn();

            expect(isWebAuthnSupported()).toBe(false);
        });

        it('should return true when PublicKeyCredential and navigator.credentials exist', () => {
            enableWebAuthn({ get: jest.fn() });

            expect(isWebAuthnSupported()).toBe(true);
        });
    });

    describe('getAssertion', () => {
        it('should reject on unsupported browsers', async () => {
            disableWebAuthn();

            await expect(getAssertion({ challenge: 'Y2hhbGxlbmdl' })).rejects.toThrow(
                'This browser does not support passkeys (WebAuthn).',
            );
        });

        it('should convert the options to buffers and serialize the assertion back to base64url', async () => {
            const authenticatorData = new Uint8Array([1]).buffer;
            const clientDataJSON = new Uint8Array([2]).buffer;
            const signature = new Uint8Array([3]).buffer;
            const rawId = new Uint8Array([4]).buffer;

            const get = jest.fn().mockResolvedValue({
                id: 'credential-id',
                rawId,
                type: 'public-key',
                response: {
                    authenticatorData,
                    clientDataJSON,
                    signature,
                    userHandle: null,
                },
            });
            enableWebAuthn({ get });

            const assertion = await getAssertion({
                challenge: 'Y2hhbGxlbmdl',
                rpId: 'shop.example',
                allowCredentials: [
                    { id: 'Y3JlZA', type: 'public-key' },
                ],
            });

            const { publicKey } = (get.mock.calls[0] as unknown[])[0] as CapturedRequestOptions;
            expect(publicKey.rpId).toBe('shop.example');
            expect(new Uint8Array(publicKey.challenge)).toEqual(new Uint8Array(base64UrlToBuffer('Y2hhbGxlbmdl')));
            expect(new Uint8Array(publicKey.allowCredentials[0].id)).toEqual(new Uint8Array(base64UrlToBuffer('Y3JlZA')));
            expect(publicKey.allowCredentials[0].type).toBe('public-key');

            expect(assertion).toEqual({
                id: 'credential-id',
                rawId: bufferToBase64Url(rawId),
                type: 'public-key',
                response: {
                    authenticatorData: bufferToBase64Url(authenticatorData),
                    clientDataJSON: bufferToBase64Url(clientDataJSON),
                    signature: bufferToBase64Url(signature),
                    userHandle: null,
                },
            });
        });

        it('should serialize the userHandle when present', async () => {
            const userHandle = new Uint8Array([9]).buffer;

            const get = jest.fn().mockResolvedValue({
                id: 'credential-id',
                rawId: new Uint8Array([4]).buffer,
                type: 'public-key',
                response: {
                    authenticatorData: new Uint8Array([1]).buffer,
                    clientDataJSON: new Uint8Array([2]).buffer,
                    signature: new Uint8Array([3]).buffer,
                    userHandle,
                },
            });
            enableWebAuthn({ get });

            const assertion = await getAssertion({ challenge: 'Y2hhbGxlbmdl' });

            expect(assertion.response.userHandle).toBe(bufferToBase64Url(userHandle));
        });

        it('should map a cancelled ceremony to a friendly error', async () => {
            const cancellation = new Error('The operation either timed out or was not allowed.');
            cancellation.name = 'NotAllowedError';

            enableWebAuthn({ get: jest.fn().mockRejectedValue(cancellation) });

            await expect(getAssertion({ challenge: 'Y2hhbGxlbmdl' })).rejects.toThrow('The passkey request was cancelled.');
        });

        it('should reject when no assertion was provided', async () => {
            enableWebAuthn({ get: jest.fn().mockResolvedValue(null) });

            await expect(getAssertion({ challenge: 'Y2hhbGxlbmdl' })).rejects.toThrow('No passkey was provided.');
        });
    });

    describe('createCredential', () => {
        it('should convert the creation options and serialize the credential back to base64url', async () => {
            const attestationObject = new Uint8Array([5]).buffer;
            const clientDataJSON = new Uint8Array([6]).buffer;
            const rawId = new Uint8Array([7]).buffer;

            const create = jest.fn().mockResolvedValue({
                id: 'credential-id',
                rawId,
                type: 'public-key',
                response: {
                    attestationObject,
                    clientDataJSON,
                },
            });
            enableWebAuthn({ create });

            const credential = await createCredential({
                challenge: 'Y2hhbGxlbmdl',
                user: {
                    id: 'dXNlci1pZA',
                    name: 'admin',
                    displayName: 'Admin',
                },
                excludeCredentials: [
                    { id: 'ZXhpc3Rpbmc', type: 'public-key' },
                ],
            });

            const { publicKey } = (create.mock.calls[0] as unknown[])[0] as CapturedCreationOptions;
            expect(new Uint8Array(publicKey.challenge)).toEqual(new Uint8Array(base64UrlToBuffer('Y2hhbGxlbmdl')));
            expect(new Uint8Array(publicKey.user.id)).toEqual(new Uint8Array(base64UrlToBuffer('dXNlci1pZA')));
            expect(publicKey.user.name).toBe('admin');
            expect(new Uint8Array(publicKey.excludeCredentials[0].id)).toEqual(
                new Uint8Array(base64UrlToBuffer('ZXhpc3Rpbmc')),
            );

            expect(credential).toEqual({
                id: 'credential-id',
                rawId: bufferToBase64Url(rawId),
                type: 'public-key',
                response: {
                    attestationObject: bufferToBase64Url(attestationObject),
                    clientDataJSON: bufferToBase64Url(clientDataJSON),
                },
            });
        });

        it('should reject when no credential was created', async () => {
            enableWebAuthn({ create: jest.fn().mockResolvedValue(null) });

            await expect(createCredential({ challenge: 'Y2hhbGxlbmdl', user: { id: 'dXNlci1pZA' } })).rejects.toThrow(
                'No passkey was created.',
            );
        });

        it('should map a cancelled ceremony to a friendly error', async () => {
            const cancellation = new Error('aborted');
            cancellation.name = 'AbortError';

            enableWebAuthn({ create: jest.fn().mockRejectedValue(cancellation) });

            await expect(createCredential({ challenge: 'Y2hhbGxlbmdl', user: { id: 'dXNlci1pZA' } })).rejects.toThrow(
                'The passkey request was cancelled.',
            );
        });
    });
});
