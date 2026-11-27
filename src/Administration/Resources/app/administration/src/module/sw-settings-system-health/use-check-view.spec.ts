/**
 * @sw-package framework
 */
import { checkViewProps, useCheckView } from './use-check-view';

describe('module/sw-settings-system-health/use-check-view', () => {
    it('should give the details and status of the last result', () => {
        const { extra, status } = useCheckView({
            check: {
                name: 'MyCheck',
                category: 'FEATURE',
                tags: [],
                result: {
                    status: 'OK',
                    healthy: true,
                    message: '',
                    extra: { code: 200 },
                    checkedAt: '2026-11-24T10:00:00+00:00',
                },
            },
        });

        expect(extra.value).toEqual({ code: 200 });
        expect(status.value).toBe('OK');
    });

    it('should give empty details for a check that never ran', () => {
        const { extra, status } = useCheckView({ check: { name: 'MyCheck', category: 'FEATURE', tags: [], result: null } });

        expect(extra.value).toEqual({});
        expect(status.value).toBeNull();
    });

    it('should require the check', () => {
        expect(checkViewProps.check.required).toBe(true);
    });
});
