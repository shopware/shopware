/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('src/app/plugin/deprecation.plugin', () => {
    let warn: jest.SpyInstance;

    beforeEach(() => {
        warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    describe('deprecated component', () => {
        it.activeFeatureFlags(['v6.8.0.0']).each(['v6.8.0.0', '6.8'])(
            'throws once the major of "%s" is active',
            (version) => {
                const legacy = { name: 'sw-legacy', template: '<div />', deprecated: version };

                expect(() => mount(legacy)).toThrow(
                    `Tried to access deprecated functionality: The component "sw-legacy" is deprecated and will be removed in Shopware ${version}.`,
                );
            },
        );

        // v7.0.0.0 is a major that no test suite activates
        it('warns with the comment and where it is used while the major is inactive', () => {
            mount({
                name: 'sw-page',
                components: {
                    'sw-legacy': {
                        name: 'sw-legacy',
                        template: '<div />',
                        deprecated: { version: 'v7.0.0.0', comment: 'Use "mt-select" instead.' },
                    },
                },
                template: '<sw-legacy />',
            });

            expect(warn).toHaveBeenCalledWith(
                '[Deprecation]',
                'The component "sw-legacy" is deprecated and will be removed in Shopware v7.0.0.0.\n' +
                    'Use "mt-select" instead.\n' +
                    'Used in: VTU_ROOT > sw-page > sw-legacy',
            );
        });

        it('warns once per usage site', () => {
            const legacy = { name: 'sw-repeated-legacy', template: '<div />', deprecated: 'v7.0.0.0' };

            mount({
                name: 'sw-list',
                components: { 'sw-repeated-legacy': legacy },
                template: '<div><sw-repeated-legacy /><sw-repeated-legacy /></div>',
            });

            mount({
                name: 'sw-grid',
                components: { 'sw-repeated-legacy': legacy },
                template: '<sw-repeated-legacy />',
            });

            expect(warn).toHaveBeenCalledTimes(2);
        });
    });

    describe('deprecated prop', () => {
        const legacy = {
            name: 'sw-image',
            template: '<div />',
            props: {
                emptyImagePath: { type: String, default: '/empty.svg', deprecated: 'v6.8.0.0' },
                emptyImages: { type: Array, default: (): string[] => [], deprecated: 'v6.8.0.0' },
                emptyIcon: { type: String, deprecated: 'v6.8.0.0' },
            },
        };

        it.activeFeatureFlags(['v6.8.0.0'])('throws once the major is active and the parent passes the prop', () => {
            const parent = {
                name: 'sw-page',
                components: { 'sw-image': legacy },
                template: '<sw-image empty-icon="regular-image" />',
            };

            expect(() => mount(parent)).toThrow(
                'Tried to access deprecated functionality: The prop "emptyIcon" of the component "sw-image" is deprecated and will be removed in Shopware v6.8.0.0.',
            );
        });

        it.activeFeatureFlags(['v6.8.0.0'])('does not guard props the parent does not pass', () => {
            const parent = {
                name: 'sw-page',
                components: { 'sw-image': legacy },
                template: '<sw-image :empty-icon="undefined" />',
            };

            expect(() => mount(parent)).not.toThrow();
        });

        it('warns with the comment and where it is used while the major is inactive', () => {
            mount({
                name: 'sw-page',
                components: {
                    'sw-image': {
                        name: 'sw-image',
                        template: '<div />',
                        props: {
                            emptyIcon: {
                                type: String,
                                deprecated: { version: 'v7.0.0.0', comment: 'Use "placeholderIcon" instead.' },
                            },
                        },
                    },
                },
                template: '<sw-image empty-icon="regular-image" />',
            });

            expect(warn).toHaveBeenCalledWith(
                '[Deprecation]',
                'The prop "emptyIcon" of the component "sw-image" is deprecated and will be removed in Shopware v7.0.0.0.\n' +
                    'Use "placeholderIcon" instead.\n' +
                    'Used in: VTU_ROOT > sw-page > sw-image',
            );
        });
    });
});
