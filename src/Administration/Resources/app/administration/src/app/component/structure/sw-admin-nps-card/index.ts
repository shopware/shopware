import type { PropType } from 'vue';
import { computed, nextTick, ref, useModel } from 'vue';
import { useI18n } from 'vue-i18n';
import template from './sw-admin-nps-card.html.twig';
import './sw-admin-nps-card.scss';

/**
 * @private
 * @description Payload of the `submit` and `not-now` events.
 */
export type NpsResponse = {
    /** `null` when "Not now" is clicked before a score is selected. */
    score: number | null;
    reason: string;
};

const MIN_SCORE = 0;
const MAX_SCORE = 10;

// Scores up to and including this value (the NPS detractors, 0-6) get the follow-up question.
const FOLLOW_UP_MAX_SCORE = 6;

/**
 * @sw-package framework
 *
 * @private
 * @description Relationship NPS survey card with a 0-10 scale and an optional follow-up reason.
 * @status prototype
 * @example-type static
 * @component-example
 * <sw-admin-nps-card v-if="isNpsVisible" v-model:visible="isNpsVisible" @submit="onSubmit" />
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        /**
         * Owned by the parent (`v-model:visible`); use `v-if` on the same value to reset the card when hidden.
         */
        visible: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * Selected score (0-10), `null` when none is selected. Supports `v-model:score`.
         */
        score: {
            type: Number as PropType<number | null>,
            required: false,
            default: null,
        },

        /**
         * Optional follow-up reason. Supports `v-model:reason`.
         */
        reason: {
            type: String,
            required: false,
            default: '',
        },
    },

    emits: [
        'update:visible',
        'update:score',
        'update:reason',
        'submit',
        'not-now',
        'close',
    ],

    setup(props, { emit }) {
        const scores = Array.from({ length: MAX_SCORE - MIN_SCORE + 1 }, (_, index) => MIN_SCORE + index);

        // Parent state when bound with `v-model`, local state otherwise.
        const selectedScore = useModel(props, 'score');
        const reasonText = useModel(props, 'reason');
        const showReasonStep = ref(false);
        const showScoreError = ref(false);
        const followUpElement = ref<HTMLElement | null>(null);

        const titleId = `sw-admin-nps-card-title-${Shopware.Utils.createId()}`;
        const scoreErrorId = `sw-admin-nps-card-error-${Shopware.Utils.createId()}`;

        const i18n = useI18n();

        const needsFollowUp = computed(() => selectedScore.value !== null && selectedScore.value <= FOLLOW_UP_MAX_SCORE);

        const onSelectScore = (score: number) => {
            selectedScore.value = score;
            showScoreError.value = false;
        };

        const getResponse = (): NpsResponse => ({
            score: selectedScore.value,
            reason: reasonText.value,
        });

        const submitResponse = () => {
            emit('submit', getResponse());
            emit('update:visible', false);
        };

        const onSubmit = () => {
            if (showReasonStep.value) {
                submitResponse();
                return;
            }

            if (selectedScore.value === null) {
                showScoreError.value = true;
                return;
            }

            // Low scores are followed up with an optional reason shown in place of the question.
            if (needsFollowUp.value) {
                showReasonStep.value = true;

                // The scale the user was interacting with disappears, so move focus to the new field.
                void nextTick(() => {
                    followUpElement.value?.querySelector('textarea')?.focus();
                });
                return;
            }

            submitResponse();
        };

        const onNotNow = () => {
            emit('not-now', getResponse());
            emit('update:visible', false);
        };

        const onClose = () => {
            emit('close');
            emit('update:visible', false);
        };

        const getScoreAriaLabel = (score: number): string => {
            const baseLabel = i18n.t('global.sw-admin-nps-card.scoreAriaLabel', { score });

            if (score === MIN_SCORE) {
                return `${baseLabel} - ${i18n.t('global.sw-admin-nps-card.scoreLabelNotLikely')}`;
            }

            if (score === MAX_SCORE) {
                return `${baseLabel} - ${i18n.t('global.sw-admin-nps-card.scoreLabelExtremelyLikely')}`;
            }

            return baseLabel;
        };

        return {
            scores,
            selectedScore,
            reasonText,
            showReasonStep,
            showScoreError,
            followUpElement,
            titleId,
            scoreErrorId,
            onSelectScore,
            onSubmit,
            onNotNow,
            onClose,
            getScoreAriaLabel,
        };
    },
});
