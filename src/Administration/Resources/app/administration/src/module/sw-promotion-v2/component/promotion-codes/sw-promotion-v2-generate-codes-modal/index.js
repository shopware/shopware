import template from './sw-promotion-v2-generate-codes-modal.html.twig';
import './sw-promotion-v2-generate-codes-modal.scss';

const { Component } = Shopware;
const debounce = Shopware.Utils.debounce;

Component.register('sw-promotion-v2-generate-codes-modal', {
    template,

    inject: [
        'promotionCodeApiService',
    ],

    mixins: [
        'notification',
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isGenerating: false,
            customPatternMode: false,
            codeAmount: 10,
            preview: '',
            pattern: {
                prefix: '',
                suffix: '',
                codeLength: 5,
            },
        };
    },

    watch: {
        pattern: {
            deep: true,
            handler() {
                if (!this.customPatternMode) {
                    this.updatePattern();
                }

                this.updatePreview();
            },
        },

        'promotion.individualCodePattern'() {
            this.updatePreview();
        },

        customPatternMode() {
            if (!this.customPatternMode) {
                this.updatePattern();
            }

            this.updatePreview();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const currentPattern = this.promotion.individualCodePattern;

            if (!currentPattern || !currentPattern.length > 0) {
                this.pattern = {
                    prefix: '',
                    suffix: '',
                    codeLength: 5,
                };

                return;
            }

            const regexp = /(?<prefix>[^%]*)(?<replacement>(%[sd])+)(?<suffix>.*)/g;
            const groups = regexp.exec(currentPattern).groups;

            // The saved pattern is NOT custom, if it only consists of multiple "%s"
            this.customPatternMode = groups.replacement.includes('d');

            this.pattern = {
                ...groups,
                codeLength: groups.replacement.length / 2 || 1,
            };

            this.updatePreview();
        },

        updatePattern() {
            const characters = Array(Number(this.pattern.codeLength) + 1).join('%s');
            this.promotion.individualCodePattern = `${this.pattern.prefix}${characters}${this.pattern.suffix}`;
        },

        updatePreview: debounce(function updatePreview() {
            this.isGenerating = true;
            this.promotionCodeApiService.generatePreview(this.promotion.individualCodePattern).then((result) => {
                this.isGenerating = false;
                this.preview = result;
            });
        }, 500),

        onGenerate() {
            this.isGenerating = true;
            this.promotionCodeApiService.replaceIndividualCodes(
                this.promotion.id,
                this.promotion.individualCodePattern,
                this.codeAmount,
            ).then(() => {
                this.isGenerating = false;
                this.$emit('generate-finish');
            }).catch((e) => {
                this.isGenerating = false;
                e.response.data.errors.forEach((error) => {
                    let errorType;
                    switch (error.code) {
                        case 'PROMOTION__INDIVIDUAL_CODES_PATTERN_INSUFFICIENTLY_COMPLEX':
                            errorType = 'notComplexEnoughException';
                            break;
                        case 'PROMOTION__INDIVIDUAL_CODES_PATTERN_ALREADY_IN_USE':
                            errorType = 'alreadyInUseException';
                            break;
                        default:
                            errorType = 'unknownErrorCode';
                            break;
                    }

                    this.createNotificationError({
                        autoClose: false,
                        message: this.$tc(
                            `sw-promotion-v2.detail.base.codes.individual.generateModal.${errorType}`,
                        ),
                    });
                });
            });
        },

        onClose() {
            this.$emit('close');
        },
    },
});

