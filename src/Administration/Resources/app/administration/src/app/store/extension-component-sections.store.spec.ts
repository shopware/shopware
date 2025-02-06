import type { uiComponentSectionRenderer } from '@shopware-ag/meteor-admin-sdk/es/ui/component-section';
import type { ExtensionComponentSectionsStore } from './extension-component-sections.store';

describe('extension-component-sections.store', () => {
    let store: ExtensionComponentSectionsStore;

    beforeEach(() => {
        store = Shopware.Store.get('extensionComponentSections');
        store.identifier = {};
    });

    it('has initial state', () => {
        expect(store.identifier).toStrictEqual({});
    });

    describe('addSection action', () => {
        it('adds a new section to an empty position', () => {
            const section: Omit<uiComponentSectionRenderer, 'responseType'> & { extensionName: string } = {
                component: 'card',
                positionId: 'test-position',
                src: 'https://example.com/component',
                props: {
                    title: 'Test title',
                    locationId: 'test-location',
                },
                extensionName: 'TestExtension',
            };

            store.addSection(section);

            expect(store.identifier['test-position']).toHaveLength(1);
            expect(store.identifier['test-position'][0]).toEqual({
                component: 'card',
                src: 'https://example.com/component',
                props: {
                    title: 'Test title',
                    locationId: 'test-location',
                },
                extensionName: 'TestExtension',
            });
        });

        it('adds a new section to an existing position', () => {
            const section1: Omit<uiComponentSectionRenderer, 'responseType'> & { extensionName: string } = {
                component: 'card',
                positionId: 'test-position',
                src: 'https://example.com/component1',
                props: {
                    title: 'Test title 1',
                    locationId: 'test-location1',
                },
                extensionName: 'TestExtension1',
            };

            const section2: Omit<uiComponentSectionRenderer, 'responseType'> & { extensionName: string } = {
                component: 'card',
                positionId: 'test-position',
                src: 'https://example.com/component2',
                props: {
                    title: 'Test title 2',
                    locationId: 'test-location2',
                },
                extensionName: 'TestExtension2',
            };

            store.addSection(section1);
            store.addSection(section2);

            expect(store.identifier['test-position']).toHaveLength(2);
            expect(store.identifier['test-position'][0]).toEqual({
                component: 'card',
                src: 'https://example.com/component1',
                props: {
                    title: 'Test title 1',
                    locationId: 'test-location1',
                },
                extensionName: 'TestExtension1',
            });
            expect(store.identifier['test-position'][1]).toEqual({
                component: 'card',
                src: 'https://example.com/component2',
                props: {
                    title: 'Test title 2',
                    locationId: 'test-location2',
                },
                extensionName: 'TestExtension2',
            });
        });

        it('adds sections to different positions', () => {
            const section1: Omit<uiComponentSectionRenderer, 'responseType'> & { extensionName: string } = {
                component: 'card',
                positionId: 'position1',
                src: 'https://example.com/component1',
                props: {
                    title: 'Test title 1',
                    locationId: 'test-location1',
                },
                extensionName: 'TestExtension1',
            };

            const section2: Omit<uiComponentSectionRenderer, 'responseType'> & { extensionName: string } = {
                component: 'card',
                positionId: 'position2',
                src: 'https://example.com/component2',
                props: {
                    title: 'Test title 2',
                    locationId: 'test-location2',
                },
                extensionName: 'TestExtension2',
            };

            store.addSection(section1);
            store.addSection(section2);

            expect(store.identifier.position1).toHaveLength(1);
            expect(store.identifier.position2).toHaveLength(1);
            expect(store.identifier.position1[0]).toEqual({
                component: 'card',
                src: 'https://example.com/component1',
                props: {
                    title: 'Test title 1',
                    locationId: 'test-location1',
                },
                extensionName: 'TestExtension1',
            });
            expect(store.identifier.position2[0]).toEqual({
                component: 'card',
                src: 'https://example.com/component2',
                props: {
                    title: 'Test title 2',
                    locationId: 'test-location2',
                },
                extensionName: 'TestExtension2',
            });
        });
    });
});
