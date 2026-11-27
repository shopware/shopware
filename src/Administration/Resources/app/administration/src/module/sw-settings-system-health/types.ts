/**
 * @sw-package framework
 */

/**
 * @private
 */
export type SystemHealthStatus = 'OK' | 'UNKNOWN' | 'SKIPPED' | 'WARNING' | 'ERROR' | 'FAILURE';

/**
 * The last stored result of a check.
 *
 * @private
 */
export type SystemHealthResult = {
    status: SystemHealthStatus;
    healthy: boolean | null;
    message: string;
    extra: Record<string, unknown>;
    checkedAt: string;
};

/**
 * @private
 */
export type SystemHealthCheck = {
    name: string;
    category: string;
    // null until the check ran for the first time
    result: SystemHealthResult | null;
};

/**
 * Snippet keys of the status labels.
 *
 * @private
 */
export const STATUS_LABELS: Record<SystemHealthStatus, string> = {
    OK: 'sw-settings-system-health.status.OK',
    SKIPPED: 'sw-settings-system-health.status.SKIPPED',
    UNKNOWN: 'sw-settings-system-health.status.UNKNOWN',
    WARNING: 'global.default.warning',
    ERROR: 'global.default.error',
    FAILURE: 'sw-settings-system-health.status.FAILURE',
};

/**
 * Colors of the sw-status pill per check status.
 *
 * @private
 */
export const STATUS_COLORS: Record<SystemHealthStatus, string> = {
    OK: 'green',
    SKIPPED: 'gray',
    UNKNOWN: 'blue',
    WARNING: 'orange',
    ERROR: 'red',
    FAILURE: 'red',
};

const ATTENTION_STATUSES: SystemHealthStatus[] = [
    'WARNING',
    'ERROR',
    'FAILURE',
    'UNKNOWN',
];

/**
 * @private
 */
export function needsAttention(check: SystemHealthCheck): boolean {
    return !!check.result && ATTENTION_STATUSES.includes(check.result.status);
}
