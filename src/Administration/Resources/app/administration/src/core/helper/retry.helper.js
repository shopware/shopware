/**
 * @private
 * @sw-package framework
 */
export default class RetryHelper {
    static async retry(fn, maxTries = 3, interval = 5000) {
        try {
            return await fn();
        } catch (error) {
            if (maxTries <= 0 || !RetryHelper.isRetryable(error)) {
                throw error;
            }

            await new Promise((resolve) => {
                setTimeout(resolve, interval);
            });

            return RetryHelper.retry(fn, maxTries - 1, interval);
        }
    }

    static isRetryable(error) {
        if (!error?.isAxiosError || error.code === 'ERR_CANCELED') {
            return false;
        }

        const status = error.response?.status;

        return status === undefined || status === 408 || status === 429 || status >= 500;
    }
}
