import template from './sw-promotion-v2-generate-codes-modal.html.twig';
import './sw-promotion-v2-generate-codes-modal.scss';

const { Component } = Shopware;

Component.register('sw-promotion-v2-generate-codes-modal', {
    template,

    props: {
        promotion: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            customPatternMode: false,
            codeAmount: 10,
            pattern: {
                prefix: '',
                suffix: '',
                codeLength: 5
            }
        };
    },

    computed: {
        preview() {
            // ToDo NEXT-12515 - Generate Preview
            return this.promotion.individualCodePattern;
        }
    },

    watch: {
        'pattern.prefix'() {
            this.updatePattern();
        },

        'pattern.suffix'() {
            this.updatePattern();
        },

        'pattern.codeLength'() {
            this.updatePattern();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const pattern = this.promotion.individualCodePattern;
            if (!pattern || !pattern.length > 0) {
                this.pattern = {
                    pattern: '',
                    suffix: '',
                    codeLength: 5
                };

                return;
            }

            const regexp = /(?<prefix>[^%]*)(?<replacement>[%(s|d)]+)(?<suffix>.*)/g;
            const groups = regexp.exec(pattern).groups;
            this.pattern = {
                ...groups,
                codeLength: groups.replacement.length / 2 || 1
            };
        },

        updatePattern() {
            const characters = Array(Number(this.pattern.codeLength) + 1).join('%s');

            this.promotion.individualCodePattern = `${this.pattern.prefix}${characters}${this.pattern.suffix}`;
        },

        onGenerate() {
            // ToDo NEXT-12515 - Implement generate and remove debug
            console.log('Generate!');
        },

        onClose() {
            this.$emit('close');
        }
    }
});

