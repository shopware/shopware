/**
 * @sw-package framework
 */

import { getCurrentInstance, onScopeDispose, watch } from 'vue';
import type { Ref } from 'vue';
import { publishScopedData } from 'src/core/service/extension-api-data.service';
import type { PublishScope, publishScopedOptions } from 'src/core/service/extension-api-data.service';

/**
 * Publishes a ref to the extension API under `id`, for as long as the calling component is mounted.
 *
 * Setup-mode equivalent of `Shopware.ExtensionAPI.publishData({ id, path, scope: this })`, which
 * addressed the data as a lodash path against the component instance — a `<script setup>` component
 * exposes neither the members that path walks nor the `dataSetUnwatchers` array the teardown was
 * parked on. Handing over the ref replaces all of it.
 *
 * The channel stays two-way: an app writing into the data set writes into this ref, so the page's
 * own save picks the change up.
 *
 * @private
 */
export default function usePublishedData<T>(id: string, source: Ref<T>, options: publishScopedOptions = {}): () => void {
    const instance = getCurrentInstance();

    if (!instance) {
        Shopware.Utils.debug.warn(
            'usePublishedData',
            'Must be called during setup, so the data set can be unpublished again.',
        );

        return () => {};
    }

    const scope: PublishScope = {
        uid: instance.uid,

        read: () => source.value,

        write: (segments, value) => {
            if (segments.length === 0) {
                source.value = value as T;

                return;
            }

            const container: unknown =
                segments.length === 1
                    ? source.value
                    : Shopware.Utils.object.get(source.value, segments.slice(0, -1).join('.'));

            if (!container || typeof container !== 'object') {
                return;
            }

            (container as Record<string, unknown>)[segments[segments.length - 1]] = value;
        },

        watch: (callback) => watch(source, (value) => callback(value), { deep: true, immediate: true }),

        onTeardown: (teardown) => onScopeDispose(teardown),
    };

    return publishScopedData(id, scope, options);
}
