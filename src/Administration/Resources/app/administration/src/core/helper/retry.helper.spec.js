/**
 * @sw-package framework
 */
import retryHelper from './retry.helper';

function createResponseError(status) {
    return Object.assign(new Error(`Request failed with status code ${status}`), { response: { status } });
}

describe('core/helper/retry.helper.js', () => {
    it('retries a request that got no response', async () => {
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

    it.each([429, 503])('retries a response with status %d', async (status) => {
        const error = createResponseError(status);
        const innerFunction = jest.fn(() => {
            throw error;
        });

        await expect(retryHelper.retry(innerFunction, 1, 1)).rejects.toBe(error);

        expect(innerFunction).toHaveBeenCalledTimes(2);
    });

    it('does not retry a request the server rejected', async () => {
        const error = createResponseError(400);
        const innerFunction = jest.fn(() => {
            throw error;
        });

        await expect(retryHelper.retry(innerFunction, 3, 1)).rejects.toBe(error);

        expect(innerFunction).toHaveBeenCalledTimes(1);
    });
});
