/**
 * @sw-package framework
 */
import type { SystemHealthCheck } from './types';

type Translate = (key: string, values?: Record<string, unknown>, plural?: number) => string;

type Presenter = {
    // the "What happened" sentence, built from the check's details
    summary?: (extra: Record<string, unknown>, status: string, t: Translate) => string | null;
    // the details as they should be shown, when the check's own shape is not readable
    extra?: (extra: Record<string, unknown>) => Record<string, unknown>;
};

/**
 * Admin-side wording for core checks that only return technical messages and details.
 */
const PRESENTERS: Record<string, Presenter> = {
    // the details are the invalid locale codes, keyed by locale id
    LocalesReadiness: {
        summary: (extra, status, t) => {
            const count = Object.keys(extra).length;

            return count > 0 ? t('sw-settings-system-health.presenter.LocalesReadiness.summary', { count }, count) : null;
        },
        extra: (extra) => {
            const codes = Object.values(extra);

            return codes.length > 0 ? { invalidLocales: codes.join(', ') } : {};
        },
    },
    AdministrationReadiness: {
        summary: (extra, status, t) => {
            const missing = (extra.missingArtifactsForJsBundles ?? []) as string[];
            if (missing.length > 0) {
                return t('sw-settings-system-health.presenter.AdministrationReadiness.missing', {
                    bundles: missing.join(', '),
                });
            }

            return extra.indexPageJsBundlesFound === 0
                ? t('sw-settings-system-health.presenter.AdministrationReadiness.noScripts')
                : t('sw-settings-system-health.presenter.AdministrationReadiness.notLoading');
        },
    },
    AppUrlVerification: {
        summary: (extra, status, t) =>
            t(`sw-settings-system-health.presenter.AppUrlVerification.${status}`, { appUrl: extra.appUrl ?? '' }),
    },
};

/**
 * @private
 */
export function presentedSummary(check: SystemHealthCheck, t: Translate): string | null {
    if (!check.result) {
        return null;
    }

    return PRESENTERS[check.name]?.summary?.(check.result.extra, check.result.status, t) ?? null;
}

/**
 * @private
 */
export function presentedExtra(check: SystemHealthCheck): Record<string, unknown> {
    const extra = check.result?.extra ?? {};

    return PRESENTERS[check.name]?.extra?.(extra) ?? extra;
}
