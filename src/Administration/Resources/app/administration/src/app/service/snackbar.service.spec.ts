/**
 * @sw-package framework
 */
import { useSnackbar } from '@shopware-ag/meteor-component-library';
import SnackbarService from './snackbar.service';

jest.mock('@shopware-ag/meteor-component-library', () => ({
    useSnackbar: jest.fn(),
}));

describe('src/app/service/snackbar.service.ts', () => {
    const snackbar = {
        addSnackbar: jest.fn(),
        removeSnackbar: jest.fn(),
    };

    beforeEach(() => {
        jest.mocked(useSnackbar).mockReturnValue(snackbar as ReturnType<typeof useSnackbar>);
    });

    it('adds a snackbar to the global snackbar', () => {
        const config = {
            id: 'plugin-snackbar',
            message: 'Plugin snackbar',
            variant: 'info',
        };
        const service = new SnackbarService();

        service.addSnackbar(config);

        expect(snackbar.addSnackbar).toHaveBeenCalledWith(config);
    });

    it('removes a snackbar from the global snackbar', () => {
        const service = new SnackbarService();

        service.removeSnackbar('plugin-snackbar');

        expect(snackbar.removeSnackbar).toHaveBeenCalledWith('plugin-snackbar');
    });
});
