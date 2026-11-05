import { createPinia, setActivePinia } from 'pinia';
import {
    type AdvancedModeDependencies,
    createGeneralTabSettings,
    createSpecificationsTabSettings,
    useAdvancedMode,
} from '../private';

/**
 * @sw-package inventory
 */

const store = () => Shopware.Store.get('swProductDetail');

/**
 * The dependencies of the advanced mode, whose settings live in the store, as on the real page.
 */
function createDependencies(productId: string | null = 'product-id'): AdvancedModeDependencies {
    return {
        productId: () => productId,
        currentUser: () => ({ id: 'user-id' }),
        advancedModeSetting: () => store().advancedModeSetting as never,
        defaultSettings: () => [...createGeneralTabSettings(), ...createSpecificationsTabSettings()],
        notifyError: jest.fn(),
        translate: (key: string) => key,
        nextTick: () => Promise.resolve(),
    };
}

// The deprecated advanced mode lets a merchant hide cards of the general and specifications tabs of a product.
// The user config keeps the choice per user, and every card the user never toggled shows.
describe('the advanced mode of the product detail page', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        store().$reset();
    });

    /**
     * @requirement A merchant who creates a product sees every card.
     * @deprecated tag:v6.8.0 - The test will be removed with this module.
     */
    it('shows every card of a new product', () => {
        // ACT
        useAdvancedMode({}, createDependencies(null)).initAdvancedModeSettings();

        // ASSERT
        expect(store().modeSettings).toEqual([
            ...createGeneralTabSettings().map(({ key }) => key),
            ...createSpecificationsTabSettings().map(({ key }) => key),
        ]);
    });

    /**
     * @requirement A merchant who hid a card does not see it on the next product either.
     * @deprecated tag:v6.8.0 - The test will be removed with this module.
     */
    it('hides the cards the user hid before', async () => {
        // ARRANGE
        jest.spyOn(Shopware.Service('userConfigService'), 'search').mockResolvedValue({
            data: {
                'mode.setting.advancedModeSettings': {
                    advancedMode: { label: 'sw-product.general.textAdvancedMode', enabled: true },
                    settings: [
                        { key: 'media', label: 'sw-product.detailBase.cardTitleMedia', enabled: false, name: 'general' },
                    ],
                },
            },
        } as never);

        // ACT
        await useAdvancedMode({}, createDependencies()).getAdvancedModeSetting();

        // ASSERT
        expect(store().modeSettings).not.toContain('media');
        expect(store().modeSettings).toContain('prices');
    });
});
