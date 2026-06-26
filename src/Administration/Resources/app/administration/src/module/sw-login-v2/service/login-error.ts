/**
 * @sw-package framework
 */

interface JsonApiError {
    status?: string;
    code?: string;
    meta?: {
        parameters?: {
            seconds?: number;
        };
    };
}

interface ParsedApiError {
    status?: number;
    code?: string;
    retryAfterSeconds?: number;
}

/**
 * Normalizes an axios/JSON:API rejection into the few fields the login views care about.
 * Returns an empty object for network errors / non-API rejections (no `response`).
 *
 * @private
 */
export function parseApiRejection(error: unknown): ParsedApiError {
    const apiError = firstApiError(error);

    if (!apiError) {
        return {};
    }

    const status = Number.parseInt(apiError.status ?? '', 10);

    return {
        status: Number.isNaN(status) ? undefined : status,
        code: apiError.code,
        retryAfterSeconds: apiError.meta?.parameters?.seconds,
    };
}

function firstApiError(error: unknown): JsonApiError | undefined {
    if (typeof error !== 'object' || error === null) {
        return undefined;
    }

    const errors = (error as { response?: { data?: { errors?: unknown } } }).response?.data?.errors;

    if (Array.isArray(errors)) {
        return errors[0] as JsonApiError | undefined;
    }

    if (typeof errors === 'object' && errors !== null) {
        return errors as JsonApiError;
    }

    return undefined;
}
