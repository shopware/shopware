/**
 * @sw-package framework
 */

import { computed, defineComponent, ref, type PropType } from 'vue';
import { diffLines } from '../model/line-diff';
import { compactTextDiff, LONG_TEXT_LENGTH } from '../model/text-diff';
import type { ApiChange } from '../../diff/types';
import { pillInfo, type PrivateChangeFields, type Severity } from '../model/severity';

/** Pass `change` to get the combined pill of private changes, e.g. "Private · Breaking". */
export const SeverityPill = defineComponent({
    name: 'SeverityPill',
    props: {
        severity: { type: String as PropType<Severity | null>, default: null },
        change: { type: Object as PropType<(Pick<ApiChange, 'severity'> & PrivateChangeFields) | null>, default: null },
        /** Count shown in front of the label, e.g. 3 for "3 Breaking". */
        count: { type: Number as PropType<number | null>, default: null },
        /** Replaces the label, e.g. '3 Breaking'. */
        text: { type: String as PropType<string | null>, default: null },
        small: { type: Boolean, default: false },
    },
    setup(props) {
        const info = computed(() => pillInfo(props.change ?? { severity: props.severity ?? 'compatible' }));

        return { info };
    },
    template: `
        <span class="pill" :class="{ 'pill--small': small, 'pill--private': info.privatePrefix }" :data-severity="info.tone">
            <span class="pill__symbol" aria-hidden="true">{{ info.symbol }}</span><template v-if="text">{{ text }}</template><template v-else><template v-if="count !== null">{{ count + ' ' }}</template><span v-if="info.privatePrefix" class="pill__private">{{ info.privatePrefix }} · </span>{{ info.label }}</template>
        </span>
    `,
});

/** Renders `backtick` spans of diff reasons as code. */
export const ReasonText = defineComponent({
    name: 'ReasonText',
    props: {
        text: { type: String, required: true },
    },
    setup(props) {
        const parts = computed(() =>
            props.text.split('`').map((value, index) => {
                const code = index % 2 === 1;
                const long = code && value.length > LONG_TEXT_LENGTH;

                return { value: long ? `${value.slice(0, 60)}…` : value, full: long ? value : null, code, key: index };
            }),
        );

        return { parts };
    },
    template: `<span><template v-for="part in parts" :key="part.key"><code v-if="part.code" class="inline-code" :title="part.full ?? undefined">{{ part.value }}</code><template v-else>{{ part.value }}</template></template></span>`,
});

/** Before/after of a long type or signature: only the differing part, highlighted, with a toggle for the full text. */
export const TypeDiff = defineComponent({
    name: 'TypeDiff',
    props: {
        before: { type: String, required: true },
        after: { type: String, required: true },
        beforeLabel: { type: String, default: 'Before' },
        afterLabel: { type: String, default: 'After' },
    },
    setup(props) {
        const open = ref(false);
        const segments = computed(() => compactTextDiff(props.before, props.after));

        return {
            open,
            segments,
            toggle: () => {
                open.value = !open.value;
            },
        };
    },
    template: `
        <div class="type-diff" data-testid="type-diff">
            <code class="type-diff__inline"><template v-for="(segment, index) in segments" :key="index"><del v-if="segment.type === 'removed'" class="type-diff__removed"><span class="visually-hidden">removed: </span>{{ segment.text }}</del><ins v-else-if="segment.type === 'added'" class="type-diff__added"><span class="visually-hidden">added: </span>{{ segment.text }}</ins><span v-else-if="segment.type === 'gap'" class="type-diff__gap">{{ segment.text }}</span><template v-else>{{ segment.text }}</template></template></code>
            <button type="button" class="link-button" :aria-expanded="open ? 'true' : 'false'" @click="toggle">{{ open ? 'Hide full types' : 'Show full types' }}</button>
            <div v-if="open" class="stack stack--tight" data-testid="type-diff-full">
                <span class="eyebrow eyebrow--before">{{ beforeLabel }}</span>
                <code class="signature signature--before">{{ before }}</code>
                <span class="eyebrow eyebrow--after">{{ afterLabel }}</span>
                <code class="signature signature--after">{{ after }}</code>
            </div>
        </div>
    `,
});

export const LineDiff = defineComponent({
    name: 'LineDiff',
    props: {
        before: { type: String as PropType<string | null>, default: null },
        after: { type: String as PropType<string | null>, default: null },
        title: { type: String, required: true },
        caption: { type: String, default: '' },
    },
    setup(props) {
        const lines = computed(() =>
            diffLines(props.before, props.after).map((line, index) => ({
                ...line,
                key: index,
                sign: { same: ' ', added: '+', removed: '−' }[line.type],
            })),
        );

        return { lines };
    },
    template: `
        <div class="code-diff">
            <div class="code-diff__header">
                <span class="code-diff__title">{{ title }}</span>
                <span class="mono">{{ caption }}</span>
            </div>
            <div class="code-diff__body" tabindex="0" :aria-label="title">
                <div v-if="lines.length === 0" class="code-diff__empty">No content.</div>
                <div v-for="line in lines" :key="line.key" class="code-diff__line" :data-type="line.type">
                    <span class="code-diff__sign" aria-hidden="true">{{ line.sign }}</span>
                    <span class="visually-hidden" v-if="line.type !== 'same'">{{ line.type }}:</span>
                    <span class="code-diff__text">{{ line.text }}</span>
                </div>
            </div>
        </div>
    `,
});

export const SegmentedToggle = defineComponent({
    name: 'SegmentedToggle',
    props: {
        modelValue: { type: String, required: true },
        options: { type: Array as PropType<Array<{ value: string; label: string }>>, required: true },
        label: { type: String, default: 'View' },
    },
    emits: ['update:modelValue'],
    template: `
        <div class="segmented" role="group" :aria-label="label">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                class="segmented__button"
                :aria-pressed="modelValue === option.value ? 'true' : 'false'"
                @click="$emit('update:modelValue', option.value)"
            >{{ option.label }}</button>
        </div>
    `,
});
