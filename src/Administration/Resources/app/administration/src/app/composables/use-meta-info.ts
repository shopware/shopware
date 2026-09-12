/**
 * @sw-package framework
 */

import { watchEffect } from 'vue';

/** @private */
export interface MetaInfo {
    title?: string;
}

/**
 * Keeps `document.title` in sync with the value its getter returns, for as long as the calling
 * component is mounted.
 *
 * Setup-mode equivalent of the `metaInfo` option, which the meta-info plugin read off the component
 * type and then called with the instance — so its body reached members a `<script setup>` component
 * does not expose. Passing the getter directly removes that lookup.
 *
 * Keep this and `src/app/plugin/meta-info.plugin.js` in sync — change both together.
 *
 * @private
 */
export default function useMetaInfo(getMetaInfo: () => MetaInfo): void {
    watchEffect(() => {
        const metaInfo = getMetaInfo();

        if (metaInfo && typeof metaInfo === 'object' && metaInfo.title !== undefined) {
            document.title = metaInfo.title;
        }
    });
}
