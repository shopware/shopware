import template from './sw-experience-studio-element-picker.html.twig';
import './sw-experience-studio-element-picker.scss';

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
    ],

    computed: {
        groupedElements(): Array<{
            key: string;
            headlineSnippetKey: string;
            elements: Array<{ name: string; label: string; icon: string | null }>;
        }> {
            type Group = {
                key: string;
                headlineSnippetKey: string;
                elements: Array<{ name: string; label: string; icon: string | null }>;
                firstSeenIndex: number;
            };

            const groups = this.elements.reduce<Group[]>((result, element, index) => {
                const categoryKey = this.normalizeCategoryKey((element as { category?: string | null }).category ?? null);
                const existingGroup = result.find((group) => group.key === categoryKey);

                if (existingGroup) {
                    existingGroup.elements.push(element as { name: string; label: string; icon: string | null });

                    return result;
                }

                result.push({
                    key: categoryKey,
                    headlineSnippetKey: this.categoryHeadlineSnippetKey(categoryKey),
                    elements: [element as { name: string; label: string; icon: string | null }],
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

        onSelect(component: string): void {
            this.$emit('select', component);
        },
    },
});
