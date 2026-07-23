/**
 * @sw-package framework
 */
import { type Snackbar, useSnackbar } from '@shopware-ag/meteor-component-library';
import { ref } from 'vue';
import SnackbarService from './snackbar.service';

jest.mock('@shopware-ag/meteor-component-library', () => ({
    useSnackbar: jest.fn(),
}));

describe('src/app/service/snackbar.service.ts', () => {
    const snackbar = {
        snackbars: ref([]),
        addSnackbar: jest.fn(),
        removeSnackbar: jest.fn(),
        clearSnackbars: jest.fn(),
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
