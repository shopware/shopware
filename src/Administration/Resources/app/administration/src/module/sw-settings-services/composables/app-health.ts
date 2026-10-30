/**
 * @sw-package framework
 */
import type { AppHealth, ServiceDescription } from '../service/shopware-services.service';

/**
 * @private
 */
export type HealthLevel = 'ERROR' | 'WARNING';

/**
 * Health only refines an active service: inactive services and services awaiting permissions keep their own state.
 *
 * @private
 */
export default function getHealthLevel(service: ServiceDescription, health: AppHealth | null): HealthLevel | null {
    if (!health || service.state !== 'active') {
        return null;
    }

    return health.status === 'ERROR' || health.status === 'WARNING' ? health.status : null;
}
