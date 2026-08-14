/**
 * @sw-package checkout
 */
import { useNotification } from 'src/app/composables/use-notification';
import { useExtensionError } from './use-extension-error';

jest.mock('vue-i18n', () => {
    const actual: object = jest.requireActual('vue-i18n');

    return { ...actual, useI18n: () => ({ t: (key: string) => `translated:${key}` }) };
});

jest.mock('src/app/composables/use-notification', () => ({
    useNotification: jest.fn(),
}));

describe('src/module/sw-extension/composables/use-extension-error', () => {
    const createNotificationError = jest.fn();
    const handleErrorResponse = jest.fn().mockReturnValue([]);

    beforeEach(() => {
        (useNotification as jest.Mock).mockReturnValue({ createNotificationError });
        jest.spyOn(Shopware, 'Service').mockImplementation(() => ({ handleErrorResponse }) as never);
    });

    afterEach(() => {
        jest.restoreAllMocks();
        jest.clearAllMocks();
    });

    it('hands the error service a translator instead of a component instance', () => {
        const errorResponse = { response: { data: { errors: [] } } };

        useExtensionError().showExtensionErrors(errorResponse);

        expect(handleErrorResponse).toHaveBeenCalledTimes(1);
        const [
            passedResponse,
            translator,
        ] = handleErrorResponse.mock.calls[0] as [unknown, { $t: (key: string) => string }];

        expect(passedResponse).toBe(errorResponse);
        expect(translator.$t('sw-extension.error')).toBe('translated:sw-extension.error');
    });

    it('raises one error notification per handled error', () => {
        handleErrorResponse.mockReturnValueOnce([
            { title: 'first', message: 'one' },
            { title: 'second', message: 'two' },
        ]);

        useExtensionError().showExtensionErrors({});

        expect(createNotificationError).toHaveBeenCalledTimes(2);
        expect(createNotificationError).toHaveBeenNthCalledWith(1, { title: 'first', message: 'one' });
        expect(createNotificationError).toHaveBeenNthCalledWith(2, { title: 'second', message: 'two' });
    });
});
