/**
 * @sw-package framework
 */
import { type Snackbar } from '@shopware-ag/meteor-component-library';
import useSnackbar from 'src/app/composables/use-snackbar';
import SnackbarService from './snackbar.service';

jest.mock('src/app/composables/use-snackbar', () => ({
    __esModule: true,
    default: jest.fn(),
}));

describe('src/app/service/snackbar.service.ts', () => {
    const snackbar = {
        addSnackbar: jest.fn(),
        removeSnackbar: jest.fn(),
    };

    beforeEach(() => {
        jest.mocked(useSnackbar).mockReturnValue(snackbar);
    });

    it('adds a snackbar to the global snackbar', () => {
        const config = {
            message: 'Plugin snackbar',
            variant: 'success',
        } satisfies Omit<Snackbar, 'id'>;
        const service = new SnackbarService();
        const addedSnackbar = { id: 'plugin-snackbar', ...config } satisfies Snackbar;
        snackbar.addSnackbar.mockReturnValue(addedSnackbar);

        const result = service.addSnackbar(config);

        expect(snackbar.addSnackbar).toHaveBeenCalledWith(config);
        expect(result).toEqual(addedSnackbar);
    });

    it('removes a snackbar from the global snackbar', () => {
        const service = new SnackbarService();

        service.removeSnackbar('plugin-snackbar');

        expect(snackbar.removeSnackbar).toHaveBeenCalledWith('plugin-snackbar');
    });
});
