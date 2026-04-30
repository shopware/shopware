/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

const SECRET_KEY_VALUE_PATTERN = /(license[-_\s]?key|secret|token|password|authorization)(["']?\s*[:=]\s*["']?)([^\s"',;]+)/gi;
const JWT_PATTERN = /eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)?/g;
const PRIVATE_KEY_PATTERN = /-----BEGIN [^-]+PRIVATE KEY-----[\s\S]*?-----END [^-]+PRIVATE KEY-----/g;
const STORE_LICENSE_PATTERN = /\b[A-Z0-9]{8,}(?:-[A-Z0-9]{4,}){2,}\b/g;
const SECRET_LIKE_VALUE_PATTERN = /\b(?:secret|token|password)[-_][A-Za-z0-9._~+/-]{4,}\b|\blicense[-_](?!(?:generator|key-file)\b)[A-Za-z0-9._~+/-]{4,}\b/gi;

export function redactSecrets(value: string, additionalSecrets: string[] = []): string {
    return additionalSecrets
        .filter((secret) => secret.length > 0)
        .reduce((redactedValue, secret) => {
            return redactedValue.replace(new RegExp(escapeRegExp(secret), 'g'), '[REDACTED]');
        }, value)
        .replace(PRIVATE_KEY_PATTERN, '[REDACTED]')
        .replace(JWT_PATTERN, '[REDACTED]')
        .replace(SECRET_KEY_VALUE_PATTERN, '$1$2[REDACTED]')
        .replace(SECRET_LIKE_VALUE_PATTERN, '[REDACTED]')
        .replace(STORE_LICENSE_PATTERN, '[REDACTED]');
}

export function redactReportValue(value: unknown): unknown {
    if (typeof value === 'string') {
        return redactSecrets(value);
    }

    if (Array.isArray(value)) {
        return value.map(redactReportValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value).map(([key, nestedValue]) => [
                key,
                isSecretKey(key) ? '[REDACTED]' : redactReportValue(nestedValue),
            ]),
        );
    }

    return value;
}

function isSecretKey(key: string): boolean {
    return /(licenseKey|secret|token|password|authorization)/i.test(key);
}

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
