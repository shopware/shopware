import { markRaw } from 'vue';
import type { CustomButton } from '@shopware-ag/meteor-component-library/dist/esm/MtTextEditorToolbar';
import type {
    ContentSystemInlineMappingToken,
    ContentSystemMappingCandidate,
} from 'src/core/service/api/content-system-mapping-candidate.api.service';
import { getMappingCandidateTranslation } from '../../util/element-mapping.util';
import { editorHtmlToTokens, tokensToEditorHtml, MAPPING_TOKEN_NODE_NAME } from '../../util/inline-mapping.util';
import { createMappingTokenNode } from '../../util/mapping-token-node';
import template from './sw-experience-studio-inline-text-field.html.twig';
import './sw-experience-studio-inline-text-field.scss';

type MinimalEditor = {
    chain: () => {
        focus: () => {
            insertContent: (content: object) => { run: () => void };
        };
    };
};

const INLINE_MAPPING_PARSE_DEBOUNCE_MS = 800;

/**
 * Rich text field for a property that accepts inline mapping tokens.
 *
 * The component owns the whole inline-mapping surface, which is why it exists rather than another branch in
 * `sw-experience-studio-settings-fields`: the editor, the token/node conversion on both sides of it, and the toolbar
 * button that inserts a mapping.
 *
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: '',
        },
        label: {
            type: String,
            required: false,
            default: null,
        },
        helpText: {
            type: String,
            required: false,
            default: null,
        },
        candidates: {
            type: Array as PropType<ContentSystemMappingCandidate[]>,
            required: false,
            default: () => [],
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['update:value'],

    data(): {
        isMappingModalOpen: boolean;
        activeEditor: MinimalEditor | null;
        tipTapConfig: object;
        customButtons: CustomButton[];
        editorHtml: string;
        isEditorReady: boolean;
        lastEmittedValue: string | null;
        parseRevision: number;
        parseTimeoutId: number | null;
        parsedTokens: ContentSystemInlineMappingToken[];
    } {
        return {
            isMappingModalOpen: false,
            activeEditor: null,
            tipTapConfig: {},
            customButtons: [],
            editorHtml: '',
            isEditorReady: false,
            lastEmittedValue: null,
            parseRevision: 0,
            parseTimeoutId: null,
            parsedTokens: [],
        };
    },

    created() {
        this.createEditorConfig();
        void this.refreshEditorValue(this.value);
    },

    beforeUnmount() {
        if (this.parseTimeoutId !== null) {
            window.clearTimeout(this.parseTimeoutId);
        }

        this.parseRevision += 1;
    },

    watch: {
        /**
         * Only a change that did not come from this field reloads the editor — an undo, or a different element being
         * selected. Recomputing on our own echo would hand the editor markup that differs from what it just produced,
         * and `mt-text-editor` answers any such difference with `setContent()`, which resets the caret to the start
         * of the document. That would put the caret at the start after every keystroke.
         */
        value(nextValue: string) {
            if (nextValue === this.lastEmittedValue) {
                return;
            }

            this.lastEmittedValue = null;
            void this.refreshEditorValue(nextValue);
        },

        /**
         * The catalogue loads asynchronously, so a text opened before it arrives shows its tokens as literal text.
         * Redrawing them as chips once it lands is safe only while the author has not started typing, for the same
         * caret reason as above.
         */
        candidates() {
            if (this.lastEmittedValue !== null) {
                return;
            }

            this.editorHtml = this.toEditorHtml(this.value);
        },
    },

    computed: {
        knownTokenTexts(): Map<string, string> {
            const canonicalTokens = new Map<string, string>();

            for (const candidate of this.candidates) {
                if (candidate.canonicalToken !== null) {
                    canonicalTokens.set(candidate.path, candidate.canonicalToken);
                }
            }

            for (const token of this.parsedTokens) {
                if (!token.malformed) {
                    canonicalTokens.set(token.path, token.canonicalToken);
                }
            }

            return canonicalTokens;
        },

        knownTokenPaths(): Set<string> {
            return new Set(this.knownTokenTexts.keys());
        },

        editorBindings(): object {
            return {
                modelValue: this.editorHtml,
                label: this.label,
                tipTapConfig: this.tipTapConfig,
                customButtons: this.customButtons,
                disabled: this.disabled || undefined,
            };
        },
    },

    methods: {
        toEditorHtml(value: string): string {
            return tokensToEditorHtml(value, this.parsedTokens, (path) => this.knownTokenPaths.has(path));
        },

        async refreshEditorValue(value: string, updateEditorMarkup = true, requestRevision?: number): Promise<void> {
            if (this.parseTimeoutId !== null) {
                window.clearTimeout(this.parseTimeoutId);
                this.parseTimeoutId = null;
            }

            const revision = requestRevision ?? ++this.parseRevision;

            try {
                const response = await Shopware.Service('contentSystemMappingCandidateService').parseInlineMappingTokens(
                    value,
                    revision,
                );
                if (revision !== this.parseRevision || response.revision !== revision) {
                    return;
                }

                this.parsedTokens = response.tokens;
                if (updateEditorMarkup) {
                    this.editorHtml = this.toEditorHtml(value);
                }
                this.isEditorReady = true;
            } catch {
                if (revision !== this.parseRevision) {
                    return;
                }

                this.parsedTokens = [];
                if (updateEditorMarkup) {
                    this.editorHtml = tokensToEditorHtml(value, [], () => false);
                }
                this.isEditorReady = true;
            }
        },

        scheduleEditorValueParse(value: string): void {
            if (this.parseTimeoutId !== null) {
                window.clearTimeout(this.parseTimeoutId);
            }

            // Invalidate any in-flight response immediately; the replacement parse runs after input settles.
            const revision = ++this.parseRevision;
            this.parseTimeoutId = window.setTimeout(() => {
                this.parseTimeoutId = null;
                void this.refreshEditorValue(value, false, revision);
            }, INLINE_MAPPING_PARSE_DEBOUNCE_MS);
        },

        /**
         * Built once, outside reactivity. The editor recreates itself when `tipTapConfig` changes identity, so a
         * computed here would throw away the author's caret on every unrelated re-render; `markRaw` additionally
         * keeps Vue from walking a tiptap extension looking for things to make reactive.
         */
        createEditorConfig(): void {
            this.tipTapConfig = markRaw({
                extensions: [
                    createMappingTokenNode({
                        resolveLabel: (path) => this.getCandidateLabel(path),
                    }),
                ],
            });

            const insertMappingButton: CustomButton = {
                name: 'swInlineMapping',
                label: this.$t('sw-experience-studio.detail.elementSettings.mapping.insertAction'),
                icon: 'regular-variables-xs',
                disabled: (_editor: unknown, globalDisabled: boolean): boolean =>
                    globalDisabled || this.candidates.length === 0,
                action: (editor: unknown): void => this.onOpenMappingModal(editor as MinimalEditor),
            } as CustomButton;

            this.customButtons = markRaw([insertMappingButton]);
        },

        /**
         * Falls back to the path when the catalogue does not describe it, matching the whole-field mapping chip: a
         * technical label is more use to the author than a blank one.
         */
        getCandidateLabel(path: string): string {
            const candidate = this.candidates.find((entry) => entry.path === path);

            if (!candidate) {
                return path;
            }

            const configured = getMappingCandidateTranslation(
                candidate.labelTranslations,
                Shopware.Store.get('session').currentLocale ?? '',
                Shopware.Context.app.fallbackLocale ?? '',
            );

            if (configured !== '') {
                return configured;
            }

            return this.$te(candidate.label) ? this.$t(candidate.label) : path;
        },

        /**
         * Keeping `editorHtml` at exactly what the editor produced is what stops `mt-text-editor` from calling
         * `setContent()` back on itself: its watcher compares `modelValue` against `editor.getHTML()` and only acts
         * when they differ. The two forms genuinely differ — a chip renders with a human label the token does not
         * carry — so the markup has to be stored, not re-derived from the tokens we emit.
         */
        onUpdateEditorHtml(html: string): void {
            this.editorHtml = html;
            this.lastEmittedValue = editorHtmlToTokens(html, this.knownTokenTexts);

            this.$emit('update:value', this.lastEmittedValue);
            this.scheduleEditorValueParse(this.lastEmittedValue);
        },

        onOpenMappingModal(editor: MinimalEditor): void {
            if (this.disabled) {
                return;
            }

            this.activeEditor = markRaw(editor);
            this.isMappingModalOpen = true;
        },

        onCloseMappingModal(): void {
            this.isMappingModalOpen = false;
            this.activeEditor = null;
        },

        /**
         * Inserts by node name, so the node is built against the editor's own schema. Nothing from our copy of
         * tiptap crosses into the editor here beyond the name and the attribute.
         */
        onSelectMapping(candidate: ContentSystemMappingCandidate): void {
            const editor = this.activeEditor;

            this.onCloseMappingModal();

            editor
                ?.chain()
                .focus()
                .insertContent({
                    type: MAPPING_TOKEN_NODE_NAME,
                    attrs: { path: candidate.path },
                })
                .run();
        },
    },
});
