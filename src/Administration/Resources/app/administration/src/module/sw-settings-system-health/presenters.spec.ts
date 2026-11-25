/**
 * @sw-package framework
 */
import { presentedExtra, presentedSummary } from './presenters';
import type { SystemHealthCheck } from './types';

function check(name: string, status: 'OK' | 'WARNING' | 'ERROR', extra: Record<string, unknown>): SystemHealthCheck {
    return {
        name,
        category: 'FEATURE',
        tags: [],
        result: { status, healthy: status === 'OK', message: '', extra, checkedAt: '2026-11-24T10:00:00+00:00' },
    };
}

const t = (key: string, values?: Record<string, unknown>) => `${key} ${JSON.stringify(values ?? {})}`;

describe('module/sw-settings-system-health/presenters', () => {
    it('should count the invalid locale codes and list them as one value', () => {
        const locales = check('LocalesReadiness', 'WARNING', { id1: 'xx_XX', id2: 'yy_YY' });

        expect(presentedSummary(locales, t)).toBe(
            'sw-settings-system-health.presenter.LocalesReadiness.summary {"count":2}',
        );
        expect(presentedExtra(locales)).toEqual({ invalidLocales: 'xx_XX, yy_YY' });
    });

    it('should explain an APP_URL that cannot be verified', () => {
        const appUrl = check('AppUrlVerification', 'ERROR', { appUrl: 'https://shop.example' });

        expect(presentedSummary(appUrl, t)).toBe(
            'sw-settings-system-health.presenter.AppUrlVerification.ERROR {"appUrl":"https://shop.example"}',
        );
    });

    it('should leave a check without a presenter as it is', () => {
        const other = check('MyCheck', 'OK', { value: 1 });

        expect(presentedSummary(other, t)).toBeNull();
        expect(presentedExtra(other)).toEqual({ value: 1 });
    });
});
