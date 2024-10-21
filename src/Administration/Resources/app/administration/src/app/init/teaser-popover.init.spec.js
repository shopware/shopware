import initTeaserButtons from 'src/app/init/teaser-popover.init';
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';

describe('src/app/init/teaser-popover.init.ts', () => {
    it('should handle __upsellingTeaserPopover', async () => {
        initTeaserButtons();

        const positionId = 'sw-test-position-id';

        await send('__upsellingTeaserPopover', {
            positionId,
            src: 'http://localhost:8080',
            component: 'button',
            props: {
                locationId: 'locationId',
                label: 'Ask AI Copilot',
            },
        });

        const teaserButtonStore = Shopware.Store.get('teaserPopover');

        expect(teaserButtonStore.identifier[positionId]).toBeDefined();
        expect(teaserButtonStore.identifier[positionId].component).toBe('button');
        expect(teaserButtonStore.identifier[positionId].props.label).toBe('Ask AI Copilot');
        expect(teaserButtonStore.identifier[positionId].props.locationId).toBe('locationId');

        await send('__upsellingTeaserPopover', {
            positionId: 'sales-channel',
            salesChannel: {
                title: 'Facebook',
                description: 'Sell products on Facebook',
                iconName: 'facebook',
            },
            popoverComponent: {
                src: 'http://localhost:8080',
                component: 'button',
                props: {
                    locationId: 'locationId',
                    label: 'Ask AI Copilot',
                },
            },
        });

        expect(teaserButtonStore.salesChannels).toHaveLength(1);
        expect(teaserButtonStore.salesChannels[0]).toStrictEqual({
            positionId: 'sales-channel',
            salesChannel: {
                title: 'Facebook',
                description: 'Sell products on Facebook',
                iconName: 'facebook',
            },
            popoverComponent: {
                src: 'http://localhost:8080',
                component: 'button',
                props: {
                    locationId: 'locationId',
                    label: 'Ask AI Copilot',
                },
            },
        });
    });
});
