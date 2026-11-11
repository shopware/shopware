/**
 * @sw-package framework
 */
import retryHelper from './retry.helper';

describe('core/helper/retry.helper.js', () => {
    it('retries three times', async () => {
        const innerFunction = jest.fn(() => {
            throw new Error();
        });

        try {
            await retryHelper.retry(innerFunction, 1, 1);
        } catch (_e) {
            // nth
        }

        expect(innerFunction).toHaveBeenCalledTimes(2);
    });

    it('stops retrying when shouldRetry rejects the error', async () => {
        const error = new Error('validation failed');
        const innerFunction = jest.fn(() => {
            throw error;
        });

        await expect(retryHelper.retry(innerFunction, 3, 1, () => false)).rejects.toBe(error);

        expect(innerFunction).toHaveBeenCalledTimes(1);
    });
});
