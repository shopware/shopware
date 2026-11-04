import { cloneDeep, getUserConfigService, useProductDetailStore } from '../dependencies';

/**
 * @sw-package inventory
 */

const ADVANCED_MODE_SETTINGS_KEY = 'mode.setting.advancedModeSettings';

/**
 * One card of the product detail page that the advanced mode can hide.
 *
 * @private
 */
export type ModeSettingItem = { key: string; label: string; enabled: boolean; name: string };

/**
 * The advanced mode settings of the current user, as the user config stores them.
 *
 * @private
 */
export type AdvancedModeSetting = {
    key: string;
    userId: string | undefined;
    value?: { advancedMode: { label: string; enabled: boolean }; settings: ModeSettingItem[] };
};

/**
 * The deprecated advanced mode of the product detail page. Each member is also a step an override can replace.
 *
 * @deprecated tag:v6.8.0 - will be removed without replacement
 * @private
 */
export interface AdvancedMode {
    /** Starts from the default settings, then loads the user's own for an existing product. */
    initAdvancedModeSettings(): void;
    createUserModeSetting(): AdvancedModeSetting;
    getAdvancedModeDefaultSetting(): AdvancedModeSetting;
    /** Loads the user's settings, with a default for every card the user never toggled. */
    getAdvancedModeSetting(): Promise<void>;
    saveAdvancedMode(): Promise<void>;
    onChangeSetting(): void;
    /** The keys of the cards the settings show. */
    changeModeSettings(): string[];
    onChangeSettingItem(): void;
}

/**
 * The members of the product detail page that the advanced mode reads and calls.
 *
 * @private
 */
export interface AdvancedModePage {
    readonly productId: string | null;
    readonly currentUser: { id: string } | null;
    readonly advancedModeSetting: AdvancedModeSetting;
    readonly getModeSettingGeneralTab: ModeSettingItem[];
    readonly getModeSettingSpecificationsTab: ModeSettingItem[];
    createNotificationError: (notification: { message: string }) => void;
    $t: (key: string) => string;
    $nextTick: () => Promise<void>;
}

/**
 * The cards of the general tab that the advanced mode can hide.
 *
 * @deprecated tag:v6.8.0 - will be removed without replacement
 * @private
 */
export function createGeneralTabSettings(): ModeSettingItem[] {
    return [
        ['general_information', 'sw-product.detailBase.cardTitleProductInfo'],
        ['prices', 'sw-product.detailBase.cardTitlePrices'],
        ['deliverability', 'sw-product.detailBase.cardTitleDeliverabilityInfo'],
        ['visibility_structure', 'sw-product.detailBase.cardTitleAssignment'],
        ['media', 'sw-product.detailBase.cardTitleMedia'],
        ['labelling', 'sw-product.detailBase.cardTitleSettings'],
    ].map(([key, label]) => ({ key, label, enabled: true, name: 'general' }));
}

/**
 * The cards of the specifications tab that the advanced mode can hide.
 *
 * @deprecated tag:v6.8.0 - will be removed without replacement
 * @private
 */
export function createSpecificationsTabSettings(): ModeSettingItem[] {
    return [
        ['measurement', 'sw-product.specifications.cardTitleMeasurement'],
        ['selling_packaging', 'sw-product.specifications.cardTitleSellingPackaging'],
        ['properties', 'sw-product.specifications.cardTitleProperties'],
        ['essential_characteristics', 'sw-product.specifications.cardTitleEssentialCharacteristics'],
        ['custom_fields', 'sw-product.specifications.cardTitleCustomFields'],
    ].map(([key, label]) => ({ key, label, enabled: true, name: 'specifications' }));
}

/**
 * Lets a merchant hide cards of the product detail page, and keeps that choice in the user config.
 *
 * @example
 * const advancedMode = useAdvancedMode({}, page);
 * advancedMode.initAdvancedModeSettings();
 *
 * @deprecated tag:v6.8.0 - will be removed without replacement
 * @private
 */
export function useAdvancedMode(
    // Until 6.8, the page passes its own methods here, so a Component.override of one applies to these calls too.
    steps: Partial<AdvancedMode>,
    page: AdvancedModePage,
): AdvancedMode {
    const advancedMode: AdvancedMode = {
        initAdvancedModeSettings,
        createUserModeSetting,
        getAdvancedModeDefaultSetting,
        getAdvancedModeSetting,
        saveAdvancedMode,
        onChangeSetting,
        changeModeSettings,
        onChangeSettingItem,
    };
    const step: AdvancedMode = { ...advancedMode, ...steps };

    function initAdvancedModeSettings() {
        useProductDetailStore().advancedModeSetting = step.getAdvancedModeDefaultSetting() as never;

        if (page.productId) {
            void step.getAdvancedModeSetting();
        } else {
            useProductDetailStore().modeSettings = step.changeModeSettings();
        }
    }

    function createUserModeSetting(): AdvancedModeSetting {
        return { key: ADVANCED_MODE_SETTINGS_KEY, userId: page.currentUser?.id };
    }

    function getAdvancedModeDefaultSetting() {
        return {
            ...step.createUserModeSetting(),
            value: {
                advancedMode: { label: 'sw-product.general.textAdvancedMode', enabled: true },
                settings: [...page.getModeSettingGeneralTab, ...page.getModeSettingSpecificationsTab],
            },
        };
    }

    async function getAdvancedModeSetting() {
        const response = (await getUserConfigService().search([ADVANCED_MODE_SETTINGS_KEY])) as {
            data?: Record<string, AdvancedModeSetting['value']>;
        } | null;
        const stored = response?.data?.[ADVANCED_MODE_SETTINGS_KEY];

        if (!stored) {
            return;
        }

        const value = cloneDeep(stored);
        const defaultSettings = step.getAdvancedModeDefaultSetting().value?.settings ?? [];
        value.settings = defaultSettings.map(
            (defaultEntry) => value.settings.find((storedEntry) => storedEntry.key === defaultEntry.key) || defaultEntry,
        );

        useProductDetailStore().advancedModeSetting = { ...step.createUserModeSetting(), value } as never;
        useProductDetailStore().modeSettings = step.changeModeSettings();

        await page.$nextTick();
    }

    function saveAdvancedMode() {
        useProductDetailStore().setLoading(['advancedMode', true]);

        return getUserConfigService()
            .upsert({ [ADVANCED_MODE_SETTINGS_KEY]: page.advancedModeSetting.value })
            .then(async () => {
                await step.getAdvancedModeSetting();
                useProductDetailStore().setLoading(['advancedMode', false]);
            })
            .catch(() => {
                page.createNotificationError({ message: page.$t('global.notification.unspecifiedSaveErrorMessage') });
            });
    }

    function onChangeSetting() {
        useProductDetailStore().advancedModeSetting = page.advancedModeSetting as never;
        void step.saveAdvancedMode();
    }

    function changeModeSettings() {
        return (page.advancedModeSetting.value?.settings ?? []).filter((item) => item.enabled).map((item) => item.key);
    }

    function onChangeSettingItem() {
        useProductDetailStore().modeSettings = step.changeModeSettings();
        void step.saveAdvancedMode();
    }

    return advancedMode;
}
