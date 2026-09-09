import type { telemetryDispatch } from '@shopware-ag/meteor-admin-sdk/es/telemetry';
import type { TrackableType } from '../../core/telemetry/types';
import { extension } from 'shopware:utils';

/**
 * @sw-package framework
 * @private
 */
export default function initializeTelemetry(): void {
    Shopware.ExtensionAPI.handle('telemetryDispatch', (payload: Omit<telemetryDispatch, 'responseType'>, additionalInfo) => {
        const event = additionalInfo._event_;
        const sourceWindow = event.source != null ? (event.source as Window) : undefined;

        Shopware.Telemetry.track({
            eventName: payload.event,
            ...(payload.data as Record<string, TrackableType>),
            source: extension.getExtensionNameByOrigin(event.origin, sourceWindow) ?? 'unknown',
        });
    });
}
