/**
 * @sw-package framework
 */

import type { App } from 'vue';
import Shepherd from 'shepherd.js';
import type { Tour, TourOptions } from 'shepherd.js';

/**
 * @private
 */
export default {
    install(app: App): void {
        app.config.globalProperties.$shepherd = (options?: TourOptions): Tour => new Shepherd.Tour(options);
    },
};
