/**
 * @sw-package framework
 */

import { resolveComponentSmokeSelection } from './components';

describe('admin-plugin-compatibility component smoke registry', () => {
    it('maps known components to smoke tags', () => {
        expect(resolveComponentSmokeSelection([
            'sw-media-library',
            'sw-settings-search',
        ])).toEqual({
            requestedComponents: [
                'sw-media-library',
                'sw-settings-search',
            ],
            cases: [
                expect.objectContaining({
                    component: 'sw-media-library',
                    tag: '@compatibility-sw-media-library',
                }),
                expect.objectContaining({
                    component: 'sw-settings-search',
                    tag: '@compatibility-sw-settings-search',
                }),
            ],
            coverageGaps: [],
        });
    });

    it('reports unknown components as coverage gaps', () => {
        expect(resolveComponentSmokeSelection([
            'sw-media-library',
            'sw-unknown-component',
            'sw-media-library',
        ])).toEqual({
            requestedComponents: [
                'sw-media-library',
                'sw-unknown-component',
            ],
            cases: [
                expect.objectContaining({
                    component: 'sw-media-library',
                }),
            ],
            coverageGaps: ['sw-unknown-component'],
        });
    });
});
