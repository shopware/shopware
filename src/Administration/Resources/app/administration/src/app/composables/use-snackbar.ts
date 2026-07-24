import { type Snackbar, useSnackbar as useMeteorSnackbar } from '@shopware-ag/meteor-component-library';

/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function useSnackbar() {
    return {
        addSnackbar(config: Omit<Snackbar, 'id'>): Snackbar {
            return useMeteorSnackbar().addSnackbar(config);
        },

        removeSnackbar(id: string): void {
            useMeteorSnackbar().removeSnackbar(id);
        },
    };
}
