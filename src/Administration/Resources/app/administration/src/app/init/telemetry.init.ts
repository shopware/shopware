import type { telemetryDispatch } from '@shopware-ag/meteor-admin-sdk/es/telemetry';
import type { TrackableType } from '../../core/telemetry/types';

/**
 * @sw-package framework
 * @private
 */
export default function initializeTelemetry(): void {
    Shopware.ExtensionAPI.handle('telemetryDispatch', (payload: Omit<telemetryDispatch, 'responseType'>, additionalInfo) => {
        Shopware.Telemetry.track({
            eventName: payload.event,
            ...(payload.data as Record<string, TrackableType>),
            source: resolveExtensionName(additionalInfo._event_.origin) ?? 'unknown',
        });
    });
}

/**
 * @private
 */
export function resolveExtensionName(origin: string): string | undefined {
    const extensions = Shopware.Store.get('extensions').extensionsState;

    try {
        const incomingOrigin = new URL(origin).origin;
        const matches = Object.entries(extensions).filter(
            ([
                ,
                ext,
            ]) => {
                try {
                    return new URL(ext.baseUrl).origin === incomingOrigin;
                } catch {
                    return false;
                }
            },
        );
        return matches.length === 1 ? matches[0][0] : undefined;
    } catch {
        return undefined;
    }
}
