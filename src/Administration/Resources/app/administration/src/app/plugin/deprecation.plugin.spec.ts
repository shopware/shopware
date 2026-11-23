/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { deprecations, useProductionDeprecationGuard } from 'test/_helper_/deprecations';

// v7.0.0.0 stands in for any future major. It is registered like the real ones and only on where a test
// activates it, so the cleanup of a real major leaves these tests as they are.
const legacyComponent = {
    name: 'sw-legacy',
    template: '<div />',
    deprecated: { version: 'v7.0.0.0', comment: 'Use "mt-select" instead.' },
};

const componentWithLegacyProps = {
    name: 'sw-image',
    template: '<div />',
    props: {
        emptyIcon: { type: String, deprecated: { version: 'v7.0.0.0', comment: 'Use "placeholderIcon" instead.' } },
        emptyImagePath: { type: String, default: '/empty.svg', deprecated: 'v7.0.0.0' },
        emptyImages: { type: Array, default: (): string[] => [], deprecated: 'v7.0.0.0' },
    },
};

function mountInPage(template: string, pageName = 'sw-page'): void {
    mount({
        name: pageName,
        components: { 'sw-legacy': legacyComponent, 'sw-image': componentWithLegacyProps },
        template,
    });
}

describe('src/app/plugin/deprecation.plugin', () => {
    beforeAll(() => {
        Shopware.Feature.init({ V7_0_0_0: false });
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('blames the template that renders the component or passes the prop', () => {
        const trigger = jest.spyOn(Shopware.Feature, 'triggerDeprecationOrThrow').mockImplementation(() => {});

        mountInPage('<div><sw-legacy /><sw-image empty-icon="regular-image" /></div>');

        expect(trigger.mock.calls.map(([, , options]) => options)).toEqual([
            { _blame: 'author' },
            { _blame: 'author' },
        ]);
    });

    describe('deprecated component', () => {
        it.activeFeatureFlags(['v7.0.0.0']).each(['v7.0.0.0', '7.0'])(
            'throws once the major of "%s" is active',
            (version) => {
                expect(() => mount({ ...legacyComponent, deprecated: version })).toThrow(
                    `Tried to access deprecated functionality: The component "sw-legacy" is deprecated and will be removed in Shopware ${version}.`,
                );
            },
        );

        it('reports the comment and where it is used while the major is inactive', () => {
            mountInPage('<sw-legacy />');

            expect(deprecations).toHaveTriggered(
                'The component "sw-legacy" is deprecated and will be removed in Shopware v7.0.0.0.\n' +
                    'Use "mt-select" instead.\n' +
                    'Used in: VTU_ROOT > sw-page > sw-legacy',
            );
        });

        it('warns only once for repeated uses at the same site while the major is inactive', () => {
            useProductionDeprecationGuard();
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            mountInPage('<div><sw-legacy /><sw-legacy /></div>');

            expect(warn).toHaveBeenCalledTimes(1);
        });

        it('reports each usage site while the major is inactive', () => {
            const template = '<sw-legacy />';

            mountInPage(template, 'sw-list');
            mountInPage(template, 'sw-grid');

            expect(deprecations).toHaveTriggered('Used in: VTU_ROOT > sw-list > sw-legacy');
            expect(deprecations).toHaveTriggered('Used in: VTU_ROOT > sw-grid > sw-legacy');
        });

        it('reports a removal version that is no version as an unknown flag', () => {
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});

            mount({ ...legacyComponent, deprecated: true });

            expect(error).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining('"true" is an unknown feature flag'),
            );
        });
    });

    describe('deprecated prop', () => {
        it.activeFeatureFlags(['v7.0.0.0'])('throws once the major is active and the parent passes the prop', () => {
            const template = '<sw-image empty-icon="regular-image" />';

            expect(() => mountInPage(template)).toThrow(
                'Tried to access deprecated functionality: The prop "emptyIcon" of the component "sw-image" is deprecated and will be removed in Shopware v7.0.0.0.',
            );
        });

        it.activeFeatureFlags(['v7.0.0.0'])('throws for a prop the parent passes at its default value', () => {
            const template = '<sw-image empty-image-path="/empty.svg" />';

            expect(() => mountInPage(template)).toThrow(
                'The prop "emptyImagePath" of the component "sw-image" is deprecated',
            );
        });

        it.activeFeatureFlags(['v7.0.0.0'])('does not guard props the parent does not pass', () => {
            const template = '<sw-image :empty-icon="undefined" />';

            expect(() => mountInPage(template)).not.toThrow();
        });

        it('reports the comment and where it is used while the major is inactive', () => {
            mountInPage('<sw-image empty-icon="regular-image" />');

            expect(deprecations).toHaveTriggered(
                'The prop "emptyIcon" of the component "sw-image" is deprecated and will be removed in Shopware v7.0.0.0.\n' +
                    'Use "placeholderIcon" instead.\n' +
                    'Used in: VTU_ROOT > sw-page > sw-image',
            );
        });
    });
});
