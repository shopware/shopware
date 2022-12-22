/**
 * @package admin
 */

const scheduler = typeof setImmediate === 'function' ? setImmediate : setTimeout;

export default function flushPromises() {
    return new Promise((resolve) => {
        scheduler(resolve);
    });
}
