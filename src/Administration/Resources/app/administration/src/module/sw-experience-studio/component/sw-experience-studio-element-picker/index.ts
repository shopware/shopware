import template from './sw-experience-studio-element-picker.html.twig';
import './sw-experience-studio-element-picker.scss';

type PickerItem = {
    name: string;
    label: string;
    icon: string | null;
    category?: string | null;
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

    data() {
        return {
            categoryOrder: [
                'layout',
                'content',
                'commerce',
                'presets',
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
        top: {
            type: Number,
            required: false,
            default: 0,
        },
        left: {
            type: Number,
            required: false,
            default: 0,
        },
    },

    emits: [
        'close',
        'select',
        'select-preset',
    ],

    computed: {
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

            const groups = this.elements.reduce<Group[]>((result, element, index) => {
                const categoryKey = this.normalizeCategoryKey((element as PickerItem).category ?? null);
                const existingGroup = result.find((group) => group.key === categoryKey);

                if (existingGroup) {
                    existingGroup.elements.push(element as PickerItem);

                    return result;
                }

                result.push({
                    key: categoryKey,
                    headlineSnippetKey: this.categoryHeadlineSnippetKey(categoryKey),
                    elements: [element as PickerItem],
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
                    elements: group.elements,
                }));
        },
    },

    methods: {
        flyoutStyle(): { top: string; left: string } {
            return {
                top: `${this.top}px`,
                left: `${this.left}px`,
            };
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

        itemTooltip(item: PickerItem): { message: string } {
            if (item.kind !== 'preset') {
                return { message: item.label };
            }

            const description = item.description ? `<br>${item.description}` : '';

            return { message: `<strong>${item.label}</strong>${description}` };
        },
    },
});
