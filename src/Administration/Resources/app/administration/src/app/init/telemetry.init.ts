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
            source: Shopware.Utils.extension.getExtensionNameByOrigin(additionalInfo._event_.origin) ?? 'unknown',
        });
    });
}
