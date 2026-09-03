import { parseBoxSpacing, serializeBoxSpacing, type BoxSpacingSide } from '../../util/box-spacing.util';
import template from './sw-experience-studio-box-spacing-field.html.twig';
import './sw-experience-studio-box-spacing-field.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        modelValue: {
            type: String,
            required: false,
            default: '',
        },
        label: {
            type: String,
            required: false,
            default: '',
        },
        helpText: {
            type: String,
            required: false,
            default: null,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        compact: {
            type: Boolean,
            required: false,
            default: false,
        },
        fieldKey: {
            type: String,
            required: false,
            default: '',
        },
    },

    emits: [
        'update:modelValue',
    ],

    data() {
        return {
            sides: parseBoxSpacing(''),
            isLinked: false,
            lastEmittedValue: null as string | null,
        };
    },

    watch: {
        modelValue: {
            handler(value: string) {
                if (value === this.lastEmittedValue) {
                    return;
                }

                this.lastEmittedValue = value;
                this.sides = parseBoxSpacing(value);
            },
            immediate: true,
        },
    },

    methods: {
        getSideInputId(side: BoxSpacingSide): string {
            const normalizedKey = this.fieldKey.replace(/[^a-zA-Z0-9_-]/g, '-');

            return `sw-experience-studio-box-spacing-${normalizedKey}-${side}`;
        },

        getSideAriaLabel(side: BoxSpacingSide): string {
            const sideLabels: Record<BoxSpacingSide, string> = {
                top: this.$t('sw-experience-studio.detail.elementSettings.boxSpacingTop'),
                right: this.$t('sw-experience-studio.detail.elementSettings.boxSpacingRight'),
                bottom: this.$t('sw-experience-studio.detail.elementSettings.boxSpacingBottom'),
                left: this.$t('sw-experience-studio.detail.elementSettings.boxSpacingLeft'),
            };

            const prefix = this.label ? `${this.label} ` : '';

            return `${prefix}${sideLabels[side]}`;
        },

        onSideInput(side: BoxSpacingSide, rawValue: string): void {
            if (this.isLinked) {
                this.sides = {
                    top: rawValue,
                    right: rawValue,
                    bottom: rawValue,
                    left: rawValue,
                };
            } else {
                this.sides = {
                    ...this.sides,
                    [side]: rawValue,
                };
            }

            this.emitValue();
        },

        onLinkToggle(): void {
            if (this.disabled) {
                return;
            }

            if (!this.isLinked) {
                const syncValue = this.sides.top || this.sides.right || this.sides.bottom || this.sides.left || '';

                this.sides = {
                    top: syncValue,
                    right: syncValue,
                    bottom: syncValue,
                    left: syncValue,
                };

                this.emitValue();
            }

            this.isLinked = !this.isLinked;
        },

        emitValue(): void {
            const serialized = serializeBoxSpacing(this.sides, {
                linked: this.isLinked,
                explicit: true,
            });

            this.lastEmittedValue = serialized;
            this.$emit('update:modelValue', serialized);
        },
    },
});
