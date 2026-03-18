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
});
