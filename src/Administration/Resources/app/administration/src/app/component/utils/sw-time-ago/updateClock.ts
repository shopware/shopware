import { onUnmounted } from 'vue';

let timer: ReturnType<typeof setInterval> | null = null;
const subscribers = new Set<() => void>();

/**
 * @sw-package checkout
 * @private
 */
export default function useUpdateClock(onTick: () => void) {
    if (subscribers.size === 0) {
        timer = setInterval(() => {
            subscribers.forEach((cb) => cb());
        }, 30_000);
    }
    subscribers.add(onTick);
    onTick();
    onUnmounted(() => {
        subscribers.delete(onTick);
        if (timer && !subscribers.size) {
            clearInterval(timer);
            timer = null;
        }
    });
}
