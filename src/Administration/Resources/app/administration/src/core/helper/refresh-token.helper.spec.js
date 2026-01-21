/**
 * @sw-package framework
 */

import getRefreshTokenHelper from 'src/core/helper/refresh-token.helper';

describe('core/helper/refresh-token.helper.js', () => {
    let refreshTokenHelper;
    let loginServiceMock;

    beforeEach(() => {
        jest.useFakeTimers();

        loginServiceMock = {
            refreshToken: jest.fn(),
            getToken: jest.fn(),
            logout: jest.fn(),
        };

        Shopware.Service = jest.fn(() => loginServiceMock);

        refreshTokenHelper = getRefreshTokenHelper();
        refreshTokenHelper._isRefreshing = false;
        refreshTokenHelper._subscribers = [];
        refreshTokenHelper._errorSubscribers = [];
        refreshTokenHelper._clearLogoutTimeout();
    });

    afterEach(() => {
        jest.useRealTimers();
        jest.clearAllMocks();
    });

    describe('timeout-based logout logic', () => {
        it('should call logout after timeout when no token is available', async () => {
            loginServiceMock.refreshToken.mockRejectedValue(new Error('refresh failed'));
            loginServiceMock.getToken.mockReturnValue(null);

            const promise = refreshTokenHelper.fireRefreshTokenRequest();

            await expect(promise).rejects.toBeUndefined();

            expect(loginServiceMock.logout).not.toHaveBeenCalled();

            jest.advanceTimersByTime(1000);

            expect(loginServiceMock.logout).toHaveBeenCalledTimes(1);
        });

        it('should skip logout if token becomes available during the timeout period', async () => {
            loginServiceMock.refreshToken.mockRejectedValue(new Error('refresh failed'));
            loginServiceMock.getToken.mockReturnValueOnce(null);
            loginServiceMock.getToken.mockReturnValue('new_token_from_another_tab');

            const promise = refreshTokenHelper.fireRefreshTokenRequest();

            await expect(promise).rejects.toBeUndefined();

            jest.advanceTimersByTime(1000);

            expect(loginServiceMock.logout).not.toHaveBeenCalled();
        });

        it('should only schedule one logout timeout when multiple refresh failures occur quickly', async () => {
            loginServiceMock.refreshToken.mockRejectedValue(new Error('refresh failed'));
            loginServiceMock.getToken.mockReturnValue(null);

            const promise1 = refreshTokenHelper.fireRefreshTokenRequest();
            await expect(promise1).rejects.toBeUndefined();

            const promise2 = refreshTokenHelper.fireRefreshTokenRequest();
            await expect(promise2).rejects.toBeUndefined();

            const promise3 = refreshTokenHelper.fireRefreshTokenRequest();
            await expect(promise3).rejects.toBeUndefined();

            jest.advanceTimersByTime(1000);

            expect(loginServiceMock.logout).toHaveBeenCalledTimes(1);
        });

        it('should not schedule logout if token exists when refresh fails', async () => {
            loginServiceMock.refreshToken.mockRejectedValue(new Error('refresh failed'));
            loginServiceMock.getToken.mockReturnValue('existing_token');

            const promise = refreshTokenHelper.fireRefreshTokenRequest();

            await expect(promise).rejects.toBeUndefined();

            jest.advanceTimersByTime(1000);

            expect(loginServiceMock.logout).not.toHaveBeenCalled();
        });

        it('should clear logout timeout when refresh succeeds', async () => {
            loginServiceMock.refreshToken.mockRejectedValueOnce(new Error('refresh failed'));
            loginServiceMock.getToken.mockReturnValueOnce(null);

            const failedPromise = refreshTokenHelper.fireRefreshTokenRequest();
            await expect(failedPromise).rejects.toBeUndefined();

            loginServiceMock.refreshToken.mockResolvedValueOnce('new_token');
            loginServiceMock.getToken.mockReturnValue('new_token');

            const successPromise = refreshTokenHelper.fireRefreshTokenRequest();
            await successPromise;

            jest.advanceTimersByTime(1000);

            expect(loginServiceMock.logout).not.toHaveBeenCalled();
        });
    });
});
