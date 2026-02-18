<!-- eslint-disable vue/multi-word-component-names -->
<template>
    <sw-block name="sw_settings">
        <sw-page
            class="sw-settings-index"
            :show-smart-bar="false"
        >
            <template #content>
                <sw-block name="sw_settings_content">
                    <sw-card-view class="sw-settings-index__card-view">
                        <sw-block name="sw_settings_content_card_view">
                            <div class="sw-settings__card--hero">
                                <sw-extension-component-section
                                    position-identifier="sw-settings-index"
                                />

                                <sw-block name="sw_settings_content_card_view_header">
                                    <div class="sw-settings__content-header">
                                        <h1 class="sw-settings__content-header-title">
                                            {{ $tc('sw-settings.index.title') }}
                                        </h1>

                                        <mt-search
                                            v-model="searchQuery"
                                            class="sw-settings__content-header-search"
                                            :placeholder="$t('sw-settings.index.search.placeholder')"
                                            size="small"
                                        />
                                    </div>
                                </sw-block>

                                <!-- @deprecated tag:v6.8.0 - will be removed without replacement -->
                                <mt-banner
                                    v-if="!feature.isActive('v6.8.0.0') && !hideSettingRenameBanner"
                                    class="sw-settings__content-rename-banner"
                                    variant="info"
                                    closable
                                    @close="onCloseSettingRenameBanner"
                                >
                                    <!-- eslint-disable-next-line vue/no-v-html -->
                                    <p v-html="$t('sw-settings.index.textSettingRenameBanner')" />
                                </mt-banner>

                                <sw-block name="sw_settings_content_card_content_grid">
                                    <div
                                        class="sw-settings__content-grid"
                                        position-identifier="sw-settings-index-content"
                                    >
                                        <div
                                            v-for="(settingsItems, settingsGroup) in settingsGroups"
                                            :id="`sw-settings__content-group-${settingsGroup}`"
                                            :key="settingsGroup"
                                            class="sw-settings__content-group"
                                        >
                                            <span class="sw-settings__content-group-title">
                                                <sw-highlight-text
                                                    :text="getGroupLabel(settingsGroup)"
                                                    :search-term="searchQuery"
                                                />
                                            </span>

                                            <sw-settings-item
                                                v-for="settingsItem in settingsItems"
                                                :id="settingsItem.id"
                                                :key="settingsItem.name"
                                                :label="getLabel(settingsItem)"
                                                :to="getRouteConfig(settingsItem)"
                                            >
                                                <template #icon>
                                                    <component
                                                        :is="settingsItem.iconComponent"
                                                        v-if="settingsItem.iconComponent"
                                                    />

                                                    <mt-icon
                                                        v-else
                                                        :name="settingsItem.icon"
                                                        size="16px"
                                                    />
                                                </template>

                                                <template #label>
                                                    <sw-highlight-text
                                                        :text="getLabel(settingsItem)"
                                                        :search-term="searchQuery"
                                                    />
                                                </template>
                                            </sw-settings-item>
                                        </div>
                                    </div>

                                    <div class="sw-settings-index__empty-state">
                                        <mt-empty-state
                                            v-if="Object.keys(settingsGroups).length === 0"
                                            :description="$t('sw-settings.index.search.noResultsDescription')"
                                            :headline="$t('sw-settings.index.search.noResultsHeadline')"
                                            icon="regular-cog"
                                            centered
                                        />
                                    </div>
                                </sw-block>
                            </div>
                        </sw-block>
                    </sw-card-view>
                </sw-block>
            </template>
        </sw-page>
    </sw-block>
</template>

<script setup lang="ts">
/**
 * @sw-package framework
 */
import { ref, computed, inject, getCurrentInstance } from 'vue';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import { type SettingsItem } from 'src/app/store/settings-item.store';
import './sw-settings-index.scss';

const { hasOwnProperty } = Shopware.Utils.object;

type SettingsItemHere = Omit<SettingsItem, 'label'> & {
    label?: string | { label: string; translated: boolean };
} & { privilege?: string };

const props = defineProps({});

const acl = inject<{ can: (privilege: string) => boolean }>('acl')!;
const feature = inject<{ isActive: (flag: string) => boolean }>('feature')!;
const userConfigService = inject<$TSFixMe>('userConfigService')!;

const instance = getCurrentInstance();
const $tc = (...args: $TSFixMe[]) => (instance?.proxy as $TSFixMe)?.$tc?.(...args) ?? args[0];

const {
    searchQuery,
    settingsGroups,
    hideSettingRenameBanner,
    hasPluginConfig,
    getRouteConfig,
    getLabel,
    getGroupLabel,
    itemIsQueried,
    onCloseSettingRenameBanner,
} = createExtendableSetup(
    { props, name: 'sw-settings-index' },
    () => {
        const searchQuery = ref('');

        /** @deprecated tag:v6.8.0 - Will be removed without replacement */
        const hideSettingRenameBanner = ref(true);

        const getLabel = (settingsItem: SettingsItemHere): string => {
            if (!hasOwnProperty(settingsItem, 'label')) return '';
            if (typeof settingsItem.label === 'string') return $tc(settingsItem.label) as string;
            if (typeof settingsItem.label !== 'object') return '';
            if (!hasOwnProperty(settingsItem.label, 'translated')) return '';
            if (!hasOwnProperty(settingsItem.label, 'label') || typeof settingsItem.label.label !== 'string') return '';
            if (settingsItem.label.translated) return settingsItem.label.label;
            return $tc(settingsItem.label.label) as string;
        };

        const getGroupLabel = (settingsGroup: string): string => {
            const upper = settingsGroup.charAt(0).toUpperCase() + settingsGroup.slice(1);
            return $tc(`sw-settings.index.tab${upper}`) as string;
        };

        const itemIsQueried = (label: string): boolean => {
            const query = searchQuery.value.trim().toLowerCase();
            const item = label.trim().toLowerCase();
            if (query === '') return true;
            return item.includes(query) || query.includes(item);
        };

        const settingsGroups = computed(() => {
            const labelOfSetting = (setting: SettingsItemHere) =>
                typeof setting.label === 'string' ? setting.label : (setting.label?.label ?? '');

            const mapSettings =
                (
                    mapper: (settings: SettingsItemHere[], groupName: string) => SettingsItemHere[],
                ): ((entry: [string, SettingsItemHere[]]) => [string, SettingsItemHere[]]) =>
                    ([name, settings]: [string, SettingsItemHere[]]) => [name, mapper(settings, name)];

            const filterGroup =
                (
                    predicate: (settings: SettingsItemHere[], groupName: string) => boolean,
                ): ((entry: [string, SettingsItemHere[]]) => boolean) =>
                    ([name, settings]: [string, SettingsItemHere[]]) => predicate(settings, name);

            const onlySearchResults = mapSettings((settings, groupName) => {
                if (itemIsQueried(getGroupLabel(groupName))) return settings;
                return settings.filter((setting) => itemIsQueried(getLabel(setting)));
            });

            const onlyPrivilegedSettings = mapSettings((settings) =>
                settings.filter((setting) => {
                    if (!setting.privilege) return true;
                    return acl.can(setting.privilege);
                }),
            );

            const sortSettings = mapSettings((settings) =>
                settings.sort((a, b) => {
                    const labelA = labelOfSetting(a);
                    const labelB = labelOfSetting(b);
                    return ($tc(labelA) as string).localeCompare($tc(labelB) as string);
                }),
            );

            const removeEmptyGroups = filterGroup((settings) => settings.length > 0);

            const store = Shopware.Store.get('settingsItems');

            return Object.fromEntries(
                Object.entries(store.settingsGroups)
                    .map(onlyPrivilegedSettings)
                    .map(onlySearchResults)
                    .map(sortSettings)
                    .filter(removeEmptyGroups),
            );
        });

        /** @deprecated tag:v6.8.0 - Will be removed without replacement */
        const getUserConfig = async () => {
            const response = await userConfigService.search(['settings.hideRenameBanner']);
            hideSettingRenameBanner.value = !!(response?.data?.['settings.hideRenameBanner'] as $TSFixMe)?.value;
        };

        /** @deprecated tag:v6.8.0 - Will be removed without replacement */
        const onCloseSettingRenameBanner = async () => {
            hideSettingRenameBanner.value = true;
            await userConfigService.upsert({
                'settings.hideRenameBanner': { value: true },
            });
        };

        const hasPluginConfig = () => {
            return hasOwnProperty(settingsGroups.value, 'plugins') &&
                (settingsGroups.value as Record<string, SettingsItemHere[]>).plugins.length > 0;
        };

        const getRouteConfig = (settingsItem: SettingsItemHere) => {
            if (!hasOwnProperty(settingsItem, 'to')) return {};
            if (typeof settingsItem.to === 'string') return { name: settingsItem.to };
            if (typeof settingsItem.to === 'object') return settingsItem.to;
            return {};
        };

        if (!Shopware.Feature.isActive('v6.8.0.0')) {
            void getUserConfig();
        }

        return {
            public: {
                searchQuery,
                settingsGroups,
                hideSettingRenameBanner,
                hasPluginConfig,
                getRouteConfig,
                getLabel,
                getGroupLabel,
                itemIsQueried,
                onCloseSettingRenameBanner,
            },
        };
    },
);
</script>
