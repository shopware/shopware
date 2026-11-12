/**
 * @private
 * @sw-package framework
 */
export default class RetryHelper {
    static async retry(fn, maxTries, time, shouldRetry = () => true) {
        const wait = (ms) =>
            new Promise((resolve) => {
                setTimeout(() => resolve(), ms);
            });

        const retryWithDelay = async (innerFn, retries = 3, interval = 5000) => {
            try {
                return await innerFn();
            } catch (err) {
                if (retries <= 0 || !shouldRetry(err)) {
                    return Promise.reject(err);
                }

                await wait(interval);

                return retryWithDelay(innerFn, retries - 1, interval);
            }
        };

        return retryWithDelay(fn, maxTries, time);
    }
}
