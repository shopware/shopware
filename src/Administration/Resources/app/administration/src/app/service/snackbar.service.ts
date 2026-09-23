import type { Snackbar } from '@shopware-ag/meteor-component-library';
import useSnackbar from 'src/app/composables/use-snackbar';

/**
 * @sw-package framework
 *
 * @public
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class SnackbarService {
    addSnackbar(config: Omit<Snackbar, 'id'>): Snackbar {
        return useSnackbar().addSnackbar(config);
    }

    removeSnackbar(id: string): void {
        useSnackbar().removeSnackbar(id);
    }
}
