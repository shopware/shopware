import initializeExtensionComponentSections from 'src/app/init/extension-component-sections.init';
import { send } from '@shopware-ag/admin-extension-sdk/es/channel';

describe('src/app/init/extension-component-sections.init.ts', () => {
    initializeExtensionComponentSections();

    it('should commit the component section on Extension API event', async () => {
        const positionId = 'sw-test-position-id';
        const state = Shopware.State.get('extensionComponentSections');

        expect(state.identifier[positionId]).toBeUndefined();

        await send('uiComponentSectionRenderer', {
            component: 'sw-card',
            positionId: positionId,
            props: {
                title: 'Test title',
                subtitle: 'Test subtitle',
                locationId: 'sw-test-location-id',
            },
        });

        expect(state.identifier[positionId]).toBeDefined();
        expect(state.identifier[positionId]).toHaveLength(1);
        expect(state.identifier[positionId][0].component).toBe('sw-card');
        expect(state.identifier[positionId][0].props.title).toBe('Test title');
        expect(state.identifier[positionId][0].props.subtitle).toBe('Test subtitle');
        expect(state.identifier[positionId][0].props.locationId).toBe('sw-test-location-id');
    });
});
