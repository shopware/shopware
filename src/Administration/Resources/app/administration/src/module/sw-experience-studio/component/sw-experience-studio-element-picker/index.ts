import { useId } from 'vue';
import template from './sw-experience-studio-element-picker.html.twig';
import './sw-experience-studio-element-picker.scss';

type PickerItem = {
    name: string;
    label: string;
    icon: string | null;
    illustration?: string | null;
    category?: string | null;
    priority?: number;
    kind?: 'element' | 'preset';
    id?: string;
    description?: string | null;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    setup() {
        return { tabId: useId() };
    },

    data() {
        return {
            activeTab: 'elements' as 'elements' | 'presets',
            searchTerm: '',
            failedIllustrations: [] as string[],
            categoryOrder: [
                'layout',
                'content',
                'commerce',
            ],
            fallbackCategoryKey: 'other',
        };
    },

    props: {
        open: {
            type: Boolean,
            required: true,
        },
        title: {
            type: String,
            required: true,
        },
        elements: {
            type: Array,
            required: false,
            default: () => [],
        },
        anchorElement: {
            type: Object as PropType<HTMLElement | null>,
            required: false,
            default: null,
        },
    },

    emits: [
        'close',
        'select',
        'select-preset',
    ],

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        tabItems(): Array<{ name: 'elements' | 'presets'; label: string }> {
            return [
                {
                    name: 'elements',
                    label: this.$t('sw-experience-studio.detail.elementPicker.tabElements'),
                },
                {
                    name: 'presets',
                    label: this.$t('sw-experience-studio.detail.elementPicker.tabPresets'),
                },
            ];
        },

        searchPlaceholderSnippetKey(): string {
            return this.activeTab === 'presets'
                ? 'sw-experience-studio.detail.elementPicker.searchPresetsPlaceholder'
                : 'sw-experience-studio.detail.elementPicker.searchElementsPlaceholder';
        },

        normalizedSearchTerm(): string {
            return this.searchTerm.trim().toLowerCase();
        },

        visibleElements(): PickerItem[] {
            const searchTerm = this.normalizedSearchTerm;

            return (this.elements as PickerItem[]).filter((item) => {
                const belongsToActiveTab = this.activeTab === 'presets' ? item.kind === 'preset' : item.kind !== 'preset';

                return (
                    belongsToActiveTab &&
                    (!searchTerm ||
                        item.label.toLowerCase().includes(searchTerm) ||
                        item.name.toLowerCase().includes(searchTerm))
                );
            });
        },

        emptyStateSnippetKey(): string {
            if (this.normalizedSearchTerm) {
                return 'sw-experience-studio.detail.elementPicker.noSearchResults';
            }

            return this.activeTab === 'presets'
                ? 'sw-experience-studio.detail.elementPicker.noPresets'
                : 'sw-experience-studio.detail.elementPicker.noElements';
        },

        floatingUiOptions(): { placement: 'right-start' } {
            return {
                placement: 'right-start',
            };
        },

        groupedElements(): Array<{
            key: string;
            headlineSnippetKey: string;
            elements: PickerItem[];
        }> {
            type Group = {
                key: string;
                headlineSnippetKey: string;
                elements: PickerItem[];
                firstSeenIndex: number;
            };

            const groups = this.visibleElements.reduce<Group[]>((result, element, index) => {
                const categoryKey = this.normalizeCategoryKey(element.category ?? null);
                const existingGroup = result.find((group) => group.key === categoryKey);

                if (existingGroup) {
                    existingGroup.elements.push(element);

                    return result;
                }

                result.push({
                    key: categoryKey,
                    headlineSnippetKey: this.categoryHeadlineSnippetKey(categoryKey),
                    elements: [element],
                    firstSeenIndex: index,
                });

                return result;
            }, []);

            return groups
                .sort((a, b) => {
                    const categoryOrder = this.categoryOrder;
                    const aPriority = categoryOrder.indexOf(a.key);
                    const bPriority = categoryOrder.indexOf(b.key);
                    const resolvedAPriority = aPriority === -1 ? Number.MAX_SAFE_INTEGER : aPriority;
                    const resolvedBPriority = bPriority === -1 ? Number.MAX_SAFE_INTEGER : bPriority;

                    if (resolvedAPriority !== resolvedBPriority) {
                        return resolvedAPriority - resolvedBPriority;
                    }

                    return a.firstSeenIndex - b.firstSeenIndex;
                })
                .map((group) => ({
                    key: group.key,
                    headlineSnippetKey: group.headlineSnippetKey,
                    elements: [...group.elements].sort(
                        (a, b) =>
                            (b.priority ?? 0) - (a.priority ?? 0) ||
                            a.label.localeCompare(b.label) ||
                            a.name.localeCompare(b.name),
                    ),
                }));
        },
    },

    watch: {
        open(isOpen: boolean): void {
            if (isOpen) {
                this.searchTerm = '';
            }
        },
    },

    methods: {
        illustrationSource(illustration?: string | null): string | null {
            if (!illustration || this.failedIllustrations.includes(illustration)) {
                return null;
            }

            return this.assetFilter(
                `/administration/administration/static/img/experience-studio/element-icons/${encodeURIComponent(illustration)}.svg`,
            );
        },

        onIllustrationError(illustration?: string | null): void {
            if (illustration && !this.failedIllustrations.includes(illustration)) {
                this.failedIllustrations.push(illustration);
            }
        },

        onTabKeydown(event: KeyboardEvent): void {
            if (
                ![
                    'ArrowLeft',
                    'ArrowRight',
                    'Home',
                    'End',
                ].includes(event.key)
            ) {
                return;
            }

            event.preventDefault();

            if (event.key === 'Home') {
                this.activeTab = 'elements';
            } else if (event.key === 'End') {
                this.activeTab = 'presets';
            } else {
                this.activeTab = this.activeTab === 'elements' ? 'presets' : 'elements';
            }

            (event.currentTarget as HTMLElement)
                .querySelector<HTMLButtonElement>(`[data-tab-name="${this.activeTab}"]`)
                ?.focus();
        },

        onTabChange(tabName: string): void {
            if (tabName === 'elements' || tabName === 'presets') {
                this.activeTab = tabName;
            }
        },

        normalizeCategoryKey(category: string | null): string {
            if (!category) {
                return this.fallbackCategoryKey;
            }

            const normalizedCategory = category
                .toLowerCase()
                .replace(/[^a-z0-9_-]+/g, '-')
                .replace(/^-+|-+$/g, '');

            return normalizedCategory.length > 0 ? normalizedCategory : this.fallbackCategoryKey;
        },

        categoryHeadlineSnippetKey(categoryKey: string): string {
            return `sw-experience-studio.detail.elementPicker.categoryHeadlines.${categoryKey}`;
        },

        onSelect(item: PickerItem): void {
            if (item.kind === 'preset' && item.id) {
                this.$emit('select-preset', item.id);

                return;
            }

            this.$emit('select', item.name);
        },

        itemTooltip(item: PickerItem): { message: string; disabled: boolean } {
            if (item.kind !== 'preset' || !item.description) {
                return {
                    message: '',
                    disabled: true,
                };
            }

            return {
                message: item.description,
                disabled: false,
            };
        },
    },
});
