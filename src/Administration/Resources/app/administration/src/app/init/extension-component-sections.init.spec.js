import initializeExtensionComponentSections from 'src/app/init/extension-component-sections.init';
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';

describe('src/app/init/extension-component-sections.init.ts', () => {
    beforeAll(() => {
        // Initialize component section handler
        initializeExtensionComponentSections();

        // Add dummy extension
        Shopware.State.commit('extensions/addExtension', {
            name: 'JestApp',
            baseUrl: '', // This works because the additionalInformation._event_.origin is empty
            permissions: {},
            type: 'app',
        });
    });

    it('should commit the component section on Extension API event', async () => {
        const positionId = 'sw-test-position-id';
        const extensionComponentSectionsState = Shopware.State.get('extensionComponentSections');

        expect(extensionComponentSectionsState.identifier[positionId]).toBeUndefined();

        await send('uiComponentSectionRenderer', {
            component: 'sw-card',
            positionId: positionId,
            props: {
                title: 'Test title',
                subtitle: 'Test subtitle',
                locationId: 'sw-test-location-id',
            },
        });

        expect(extensionComponentSectionsState.identifier[positionId]).toBeDefined();
        expect(extensionComponentSectionsState.identifier[positionId]).toHaveLength(1);
        expect(extensionComponentSectionsState.identifier[positionId][0].component).toBe('sw-card');
        expect(extensionComponentSectionsState.identifier[positionId][0].props.title).toBe('Test title');
        expect(extensionComponentSectionsState.identifier[positionId][0].props.subtitle).toBe('Test subtitle');
        expect(extensionComponentSectionsState.identifier[positionId][0].props.locationId).toBe('sw-test-location-id');
    });
});
