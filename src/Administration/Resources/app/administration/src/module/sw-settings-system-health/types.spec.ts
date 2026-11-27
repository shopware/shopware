/**
 * @sw-package framework
 */
import { needsAttention, type SystemHealthStatus } from './types';

function check(status: SystemHealthStatus | null) {
    return {
        name: 'MyCheck',
        category: 'FEATURE',
        result:
            status === null
                ? null
                : { status, healthy: null, message: '', extra: {}, checkedAt: '2026-11-24T10:00:00+00:00' },
    };
}

describe('module/sw-settings-system-health/types', () => {
    it.each([
        ['WARNING', true],
        ['ERROR', true],
        ['FAILURE', true],
        ['UNKNOWN', true],
        ['OK', false],
        ['SKIPPED', false],
    ] as const)('should say whether a %s result needs attention', (status, expected) => {
        expect(needsAttention(check(status))).toBe(expected);
    });

    it('should not ask for attention for a check that never ran', () => {
        expect(needsAttention(check(null))).toBe(false);
    });
});
