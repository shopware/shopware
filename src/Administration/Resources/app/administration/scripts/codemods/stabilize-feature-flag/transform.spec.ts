/**
 * @sw-package framework
 */

import { transformSource } from './transform';

describe('stabilize feature flag codemod', () => {
    it('removes the stabilized feature flag from tests with other active flags', () => {
        const source = `it.activeFeatureFlags(['STABLE_FEATURE', 'EXPERIMENTAL_FEATURE'])(
    'runs with feature flags',
    () => {},
);`;

        expect(transformSource(source, 'STABLE_FEATURE')).toBe(`it.activeFeatureFlags(['EXPERIMENTAL_FEATURE'])(
    'runs with feature flags',
    () => {},
);`);
    });

    it('turns the helper into a regular test when no active flags remain', () => {
        const source = `it.activeFeatureFlags(['STABLE_FEATURE', 'STABLE_FEATURE'])('runs with a feature flag', () => {});`;

        expect(transformSource(source, 'STABLE_FEATURE')).toBe(`it('runs with a feature flag', () => {});`);
    });

    it('updates every matching test and leaves unrelated calls unchanged', () => {
        const source = `it.activeFeatureFlags(['STABLE_FEATURE'])('first test', () => {});
it.activeFeatureFlags(['OTHER_FEATURE'])('second test', () => {});
test.activeFeatureFlags(['STABLE_FEATURE'])('third test', () => {});`;

        expect(transformSource(source, 'STABLE_FEATURE')).toBe(`it('first test', () => {});
it.activeFeatureFlags(['OTHER_FEATURE'])('second test', () => {});
test.activeFeatureFlags(['STABLE_FEATURE'])('third test', () => {});`);
    });
});
