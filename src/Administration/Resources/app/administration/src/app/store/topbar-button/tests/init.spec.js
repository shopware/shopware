/**
 * @sw-package innovation
 */
import initTopbarButtons from '../init';
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';

describe('src/app/store/topbar-button/init', () => {
    it('should handle __upsellingMenuButton', async () => {
        initTopbarButtons();

        await send('__upsellingMenuButton', {
            label: 'Test action',
            icon: 'solid-rocket',
            callback: () => {},
        });

        const buttons = Shopware.Store.get('topBarButton').buttons;
        expect(buttons).toHaveLength(1);

        const button = buttons[0];

        expect(button.hasOwnProperty('label')).toBe(true);
        expect(button.label).toBe('Test action');
    });
});
