/**
 * @sw-package framework
 */
import { AxiosError, CanceledError } from 'axios';
import retryHelper from './retry.helper';

function createResponseError(status) {
    return Object.assign(new AxiosError(`Request failed with status code ${status}`), { response: { status } });
}

describe('core/helper/retry.helper.js', () => {
    it.each([
        { name: 'a request that timed out', error: new AxiosError('timeout of 30000ms exceeded', AxiosError.ECONNABORTED) },
        { name: 'a response with status 408', error: createResponseError(408) },
        { name: 'a response with status 429', error: createResponseError(429) },
        { name: 'a response with status 503', error: createResponseError(503) },
    ])('retries $name', async ({ error }) => {
        const innerFunction = jest.fn(() => {
            throw error;
        });

        await expect(retryHelper.retry(innerFunction, 1, 1)).rejects.toBe(error);

        expect(innerFunction).toHaveBeenCalledTimes(2);
    });

    it.each([
        { name: 'a request the server rejected', error: createResponseError(400) },
        { name: 'a cancelled request', error: new CanceledError() },
        { name: 'an error that is not an axios error', error: new Error() },
    ])('does not retry $name', async ({ error }) => {
        const innerFunction = jest.fn(() => {
            throw error;
        });

        await expect(retryHelper.retry(innerFunction, 3, 1)).rejects.toBe(error);

        expect(innerFunction).toHaveBeenCalledTimes(1);
    });
});
