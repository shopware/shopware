/**
 * @sw-package framework
 *
 * Dependency-free WebAuthn / passkey client helpers.
 *
 * The backend speaks the `web-auth/webauthn-lib` JSON shape: challenges and credential ids are
 * base64url strings on the wire, but the browser `navigator.credentials` API works with
 * `ArrayBuffer`s. This module bridges both directions:
 *   - `createCredential(optionsJson)` runs the registration ceremony.
 *   - `getAssertion(optionsJson)`     runs the authentication (assertion) ceremony.
 *
 * Every helper throws clear, user-facing `Error`s when the WebAuthn API is missing or the ceremony
 * is cancelled, so callers can surface a friendly message and fall back to the normal flows.
 */

interface CredentialDescriptorJson {
    id: string;
    type: string;
    transports?: string[];
}

/**
 * `PublicKeyCredentialRequestOptions` in the webauthn-lib JSON wire format (base64url strings
 * instead of buffers).
 *
 * @private
 */
export interface PublicKeyCredentialRequestOptionsJson {
    challenge: string;
    allowCredentials?: CredentialDescriptorJson[];
    [key: string]: unknown;
}

/**
 * `PublicKeyCredentialCreationOptions` in the webauthn-lib JSON wire format.
 *
 * @private
 */
export interface PublicKeyCredentialCreationOptionsJson {
    challenge: string;
    user: {
        id: string;
        [key: string]: unknown;
    };
    excludeCredentials?: CredentialDescriptorJson[];
    [key: string]: unknown;
}

/**
 * An authentication assertion serialized back into the webauthn-lib JSON wire format.
 *
 * @private
 */
export interface SerializedAssertion {
    id: string;
    rawId: string;
    type: string;
    response: {
        authenticatorData: string;
        clientDataJSON: string;
        signature: string;
        userHandle: string | null;
    };
}

/**
 * A registration credential serialized back into the webauthn-lib JSON wire format.
 *
 * @private
 */
export interface SerializedCredential {
    id: string;
    rawId: string;
    type: string;
    response: {
        attestationObject: string;
        clientDataJSON: string;
    };
}

/**
 * Decode a base64url string into an `ArrayBuffer`.
 *
 * @private
 */
export function base64UrlToBuffer(value: string): ArrayBuffer {
    let base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    while (base64.length % 4 !== 0) {
        base64 += '=';
    }

    const binary = window.atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes.buffer;
}

/**
 * Encode an `ArrayBuffer` / typed array into a base64url string (no padding).
 *
 * @private
 */
export function bufferToBase64Url(buffer: ArrayBuffer | ArrayBufferView): string {
    let bytes: Uint8Array;

    if (buffer instanceof Uint8Array) {
        bytes = buffer;
    } else if (ArrayBuffer.isView(buffer)) {
        bytes = new Uint8Array(buffer.buffer, buffer.byteOffset, buffer.byteLength);
    } else {
        bytes = new Uint8Array(buffer);
    }

    let binary = '';
    for (let i = 0; i < bytes.length; i += 1) {
        binary += String.fromCharCode(bytes[i]);
    }

    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/**
 * Whether the current browser supports WebAuthn / passkeys.
 *
 * @private
 */
export function isWebAuthnSupported(): boolean {
    return typeof window !== 'undefined' && !!window.PublicKeyCredential && !!navigator.credentials;
}

/**
 * Throw a clear error when the platform does not support WebAuthn.
 */
function assertWebAuthnAvailable(): void {
    if (!isWebAuthnSupported()) {
        throw new Error('This browser does not support passkeys (WebAuthn).');
    }
}

/**
 * Map a thrown ceremony error to a friendlier message.
 */
function normalizeCeremonyError(error: unknown): Error {
    if (error instanceof Error && (error.name === 'NotAllowedError' || error.name === 'AbortError')) {
        return new Error('The passkey request was cancelled.');
    }

    if (error instanceof Error) {
        return error;
    }

    return new Error('The passkey request failed.');
}

/**
 * Run the registration ceremony for a `PublicKeyCredentialCreationOptions` JSON returned by the
 * backend and return the credential serialized in the `webauthn-lib` JSON shape.
 *
 * @private
 */
export async function createCredential(optionsJson: PublicKeyCredentialCreationOptionsJson): Promise<SerializedCredential> {
    assertWebAuthnAvailable();

    if (!optionsJson || typeof optionsJson !== 'object') {
        throw new Error('Invalid registration options received from the server.');
    }

    const publicKey = {
        ...optionsJson,
        challenge: base64UrlToBuffer(optionsJson.challenge),
        user: {
            ...optionsJson.user,
            id: base64UrlToBuffer(optionsJson.user.id),
        },
        excludeCredentials: (optionsJson.excludeCredentials ?? []).map((credential) => ({
            ...credential,
            id: base64UrlToBuffer(credential.id),
        })),
    } as unknown as PublicKeyCredentialCreationOptions;

    let credential: Credential | null;
    try {
        credential = await navigator.credentials.create({ publicKey });
    } catch (error) {
        throw normalizeCeremonyError(error);
    }

    if (!credential) {
        throw new Error('No passkey was created.');
    }

    const publicKeyCredential = credential as PublicKeyCredential;
    const response = publicKeyCredential.response as AuthenticatorAttestationResponse;

    return {
        id: publicKeyCredential.id,
        rawId: bufferToBase64Url(publicKeyCredential.rawId),
        type: publicKeyCredential.type,
        response: {
            attestationObject: bufferToBase64Url(response.attestationObject),
            clientDataJSON: bufferToBase64Url(response.clientDataJSON),
        },
    };
}

/**
 * Run the assertion ceremony for a `PublicKeyCredentialRequestOptions` JSON returned by the
 * backend and return the assertion serialized in the `webauthn-lib` JSON shape.
 *
 * @private
 */
export async function getAssertion(optionsJson: PublicKeyCredentialRequestOptionsJson): Promise<SerializedAssertion> {
    assertWebAuthnAvailable();

    if (!optionsJson || typeof optionsJson !== 'object') {
        throw new Error('Invalid login options received from the server.');
    }

    const publicKey = {
        ...optionsJson,
        challenge: base64UrlToBuffer(optionsJson.challenge),
        allowCredentials: (optionsJson.allowCredentials ?? []).map((credential) => ({
            ...credential,
            id: base64UrlToBuffer(credential.id),
        })),
    } as unknown as PublicKeyCredentialRequestOptions;

    let assertion: Credential | null;
    try {
        assertion = await navigator.credentials.get({ publicKey });
    } catch (error) {
        throw normalizeCeremonyError(error);
    }

    if (!assertion) {
        throw new Error('No passkey was provided.');
    }

    const publicKeyCredential = assertion as PublicKeyCredential;
    const response = publicKeyCredential.response as AuthenticatorAssertionResponse;

    return {
        id: publicKeyCredential.id,
        rawId: bufferToBase64Url(publicKeyCredential.rawId),
        type: publicKeyCredential.type,
        response: {
            authenticatorData: bufferToBase64Url(response.authenticatorData),
            clientDataJSON: bufferToBase64Url(response.clientDataJSON),
            signature: bufferToBase64Url(response.signature),
            userHandle: response.userHandle ? bufferToBase64Url(response.userHandle) : null,
        },
    };
}
