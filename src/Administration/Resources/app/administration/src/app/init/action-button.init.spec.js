import initActionButtons from 'src/app/init/action-button.init';
import { add } from '@shopware-ag/meteor-admin-sdk/es/ui/action-button';
import actionButtonStore from '../state/action-button.store';

describe('src/app/init/action-button.init.ts', () => {
    beforeEach(() => {
        if (Shopware.State.get('actionButtons')) {
            Shopware.State.unregisterModule('actionButtons');
        }

        Shopware.State.registerModule('actionButtons', actionButtonStore);
    });

    afterEach(() => {
        if (Shopware.State.get('actionButtons')) {
            Shopware.State.unregisterModule('actionButtons');
        }
    });

    it('should handle actionButtonAdd', async () => {
        initActionButtons();

        await add({
            action: 'your-app_customer-detail-action',
            entity: 'customer',
            view: 'detail',
            label: 'Test action',
            callback: () => {},
        });

        const buttons = Shopware.State.get('actionButtons').buttons;
        expect(buttons).toHaveLength(1);

        const button = buttons[0];
        expect(button.hasOwnProperty('action')).toBe(true);
        expect(button.action).toBe('your-app_customer-detail-action');
        expect(button.hasOwnProperty('entity')).toBe(true);
        expect(button.entity).toBe('customer');
        expect(button.hasOwnProperty('view')).toBe(true);
        expect(button.view).toBe('detail');
        expect(button.hasOwnProperty('label')).toBe(true);
        expect(button.label).toBe('Test action');
    });
});
