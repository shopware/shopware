/**
 * @sw-package framework
 */
import { computed } from 'vue';
import type { SystemHealthCheck } from './types';

/**
 * The props of a check's view: the check with its last result.
 *
 * @private
 */
export const checkViewProps = {
    check: {
        type: Object as PropType<SystemHealthCheck>,
        required: true,
    },
} as const;

/**
 * Shortcuts to the last result of the check a view shows.
 *
 * @private
 */
export function useCheckView(props: { check: SystemHealthCheck }) {
    return {
        extra: computed<Record<string, unknown>>(() => props.check.result?.extra ?? {}),
        status: computed<string | null>(() => props.check.result?.status ?? null),
    };
}
