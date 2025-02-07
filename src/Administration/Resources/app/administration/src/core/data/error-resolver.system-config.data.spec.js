/**
 * @sw-package framework
 */
import ErrorResolverSystemConfig from 'src/core/data/error-resolver.system-config.data';
import ShopwareError from 'src/core/data/ShopwareError';
import mock from './mocks/error-resolver.system-config.mock.json';

const errorResolverSystemConfig = new ErrorResolverSystemConfig();

describe('src/core/data/error-resolver.system-config.data.ts', () => {
    it('should handleWriteErrors throw error', () => {
        const fn = () => errorResolverSystemConfig.handleWriteErrors(null);
        expect(fn).toThrow(Error);
    });

    it('should handleWriteErrors has system error', () => {
        errorResolverSystemConfig.handleWriteErrors(mock.errorWithSystemError);

        const countSystemError = Shopware.Store.get('error').countSystemError();

        expect(countSystemError).toBe(1);
    });

    it('should handleWriteErrors has api error', () => {
        errorResolverSystemConfig.handleWriteErrors(mock.apiErrors);

        const result = Shopware.Store.get('error').getSystemConfigApiError(
            ErrorResolverSystemConfig.ENTITY_NAME,
            null,
            'dummy.key',
        );

        expect(result).toBeInstanceOf(ShopwareError);
    });

    it('should handleWriteErrors has api error with translations', () => {
        errorResolverSystemConfig.handleWriteErrors(mock.apiErrorsWithTranslation);

        const result = Shopware.Store.get('error').getSystemConfigApiError(
            ErrorResolverSystemConfig.ENTITY_NAME,
            null,
            'dummy.key',
        );

        expect(result).toEqual({});
    });

    it('should cleanWriteErrors need clean all api errors', () => {
        errorResolverSystemConfig.handleWriteErrors(mock.apiErrors);

        errorResolverSystemConfig.cleanWriteErrors();

        const result = Shopware.Store.get('error').getAllApiErrors();

        expect(result).toEqual([]);
    });
});
