import { type Snackbar, useSnackbar } from '@shopware-ag/meteor-component-library';

/**
 * @sw-package framework
 *
 * @private
 */
export default class SnackbarService {
    addSnackbar(config: Snackbar): Snackbar {
        return useSnackbar().addSnackbar(config);
    }

    removeSnackbar(id: string): void {
        useSnackbar().removeSnackbar(id);
    }
}
