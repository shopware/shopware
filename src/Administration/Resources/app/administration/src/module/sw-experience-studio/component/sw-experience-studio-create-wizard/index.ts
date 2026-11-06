import template from './sw-experience-studio-create-wizard.html.twig';
import './sw-experience-studio-create-wizard.scss';

type LayoutTypeOption = {
    value: string;
    label: string;
    icon?: string | null;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        name: {
            type: String,
            required: false,
            default: '',
        },
        selectedType: {
            type: String,
            required: false,
            default: null,
        },
        typeOptions: {
            type: Array as PropType<LayoutTypeOption[]>,
            required: false,
            default: () => [],
        },
        isLoadingTypes: {
            type: Boolean,
            required: false,
            default: false,
        },
        typeLoadError: {
            type: String,
            required: false,
            default: null,
        },
    },

    emits: [
        'update:name',
        'update:selected-type',
        'complete',
        'cancel',
    ],

    computed: {
        createWizardTitleSnippet(): string {
            return 'sw-experience-studio.createWizard.title';
        },

        createWizardDescriptionSnippet(): string {
            return 'sw-experience-studio.createWizard.description';
        },

        createWizardStartSnippet(): string {
            return 'sw-experience-studio.createWizard.start';
        },

        trimmedName(): string {
            return this.name.trim();
        },

        hasTypeLoadError(): boolean {
            return typeof this.typeLoadError === 'string' && this.typeLoadError.length > 0;
        },

        hasTypeOptions(): boolean {
            return Array.isArray(this.typeOptions) && this.typeOptions.length > 0;
        },

        isCompletable(): boolean {
            return (
                this.trimmedName.length > 0 &&
                typeof this.selectedType === 'string' &&
                this.selectedType.length > 0 &&
                !this.isLoadingTypes
            );
        },
    },

    methods: {
        getTypeOptionId(value: string): string {
            const normalized = value.replace(/[^a-zA-Z0-9_-]/g, '-');

            return `sw-experience-studio-create-wizard-type-${normalized}`;
        },

        isSelectedType(value: string): boolean {
            return this.selectedType === value;
        },

        getTypeIcon(option: LayoutTypeOption): string {
            return option.icon ?? 'regular-file';
        },

        onNameChange(value: string): void {
            this.$emit('update:name', value);
        },

        onTypeChange(value: string | null): void {
            this.$emit('update:selected-type', value);
        },

        onCancel(): void {
            this.$emit('cancel');
        },

        onComplete(): void {
            if (!this.isCompletable) {
                return;
            }

            this.$emit('complete', {
                name: this.trimmedName,
                type: this.selectedType,
            });
        },
    },
});
