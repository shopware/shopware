import initTeaserButtons from 'src/app/init/teaser-popover.init';
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';
import 'src/app/store/teaser-popover.store';

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

        const teaserButtonState = Shopware.Store.get('teaserPopoverState');

        expect(teaserButtonState.identifier[positionId]).toBeDefined();
        expect(teaserButtonState.identifier[positionId].component).toBe('button');
        expect(teaserButtonState.identifier[positionId].props.label).toBe('Ask AI Copilot');
        expect(teaserButtonState.identifier[positionId].props.locationId).toBe('locationId');

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

        expect(teaserButtonState.salesChannels).toHaveLength(1);
        expect(teaserButtonState.salesChannels[0]).toStrictEqual({
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
