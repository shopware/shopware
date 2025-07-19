/**
 * @sw-package framework
 */
// eslint-disable-next-line filename-rules/match
import { createPinia, setActivePinia } from 'pinia';
import initActionButtons from 'src/app/init/action-button.init';
import { add } from '@shopware-ag/meteor-admin-sdk/es/ui/action-button';
import type { ActionButtonsStore } from '../store/action-buttons.store';

describe('src/app/init/action-button.init.ts', () => {
    let store: ActionButtonsStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopware.Store.get('actionButtons');
    });

    it('should handle actionButtonAdd', async () => {
        initActionButtons();

        const testActionButton = {
            label: 'Test',
            name: 'test',
            entity: 'product',
            view: 'detail',
            callback: () => {},
        } as const;

        await add(testActionButton);

        const buttons = store.buttons;
        expect(buttons).toHaveLength(1);

        const button = buttons[0];
        expect(button.label).toBe(testActionButton.label);
        expect(button.name).toBe(testActionButton.name);
        expect(button.entity).toBe(testActionButton.entity);
        expect(button.view).toBe(testActionButton.view);
        expect(typeof button.callback).toBe('function');
    });
});
