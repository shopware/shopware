/**
 * @sw-package framework
 */

import { createApp } from 'vue';
import Shepherd from 'shepherd.js';
import shepherdPlugin from 'src/app/plugin/shepherd.plugin';

describe('src/app/plugin/shepherd.plugin', () => {
    it('provides a tour factory on every component', () => {
        const app = createApp({ template: '<div />' });

        app.use(shepherdPlugin);

        const tour = app.config.globalProperties.$shepherd({ tourName: 'sw-jest' });

        expect(tour).toBeInstanceOf(Shepherd.Tour);
        expect(tour.options.tourName).toBe('sw-jest');
    });
});
