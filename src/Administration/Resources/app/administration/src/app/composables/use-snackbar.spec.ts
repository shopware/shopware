/**
 * @sw-package framework
 */
import { type Snackbar, useSnackbar as useMeteorSnackbar } from '@shopware-ag/meteor-component-library';
import { ref } from 'vue';
import useSnackbar from './use-snackbar';

jest.mock('@shopware-ag/meteor-component-library', () => ({
    useSnackbar: jest.fn(),
}));

describe('src/app/composables/use-snackbar.ts', () => {
    const meteorSnackbar = {
        snackbars: ref([]),
        addSnackbar: jest.fn(),
        removeSnackbar: jest.fn(),
        clearSnackbars: jest.fn(),
    };

    beforeEach(() => {
        jest.mocked(useMeteorSnackbar).mockReturnValue(meteorSnackbar);
    });

    it('adds a snackbar', () => {
        const config = {
            message: 'Composable snackbar',
            variant: 'success',
        } satisfies Omit<Snackbar, 'id'>;
        const snackbar = { id: 'composable-snackbar', ...config } satisfies Snackbar;
        meteorSnackbar.addSnackbar.mockReturnValue(snackbar);

        expect(useSnackbar().addSnackbar(config)).toEqual(snackbar);
        expect(meteorSnackbar.addSnackbar).toHaveBeenCalledWith(config);
    });

    it('removes a snackbar', () => {
        useSnackbar().removeSnackbar('composable-snackbar');

        expect(meteorSnackbar.removeSnackbar).toHaveBeenCalledWith('composable-snackbar');
    });
});
