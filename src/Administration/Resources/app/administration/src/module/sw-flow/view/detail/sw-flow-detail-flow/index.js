import template from './sw-flow-detail-flow.html.twig';
import './sw-flow-detail-flow.scss';

const { Component } = Shopware;

Component.register('sw-flow-detail-flow', {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            flow: {}
        };
    },

    computed: {
        getParentSequences() {
            return this.flow.flowSequences.filter(item => !item.parentId);
        }
    },

    methods: {
        onEventChange(eventName) {
            if (this.flow.eventName && this.flow?.flowSequences?.length) {
                this.flow.eventName = eventName;

                return;
            }

            this.flow = {
                eventName,
                flowSequences: [{
                    id: 1,
                    parentId: null,
                    ruleId: null,
                    actionName: null,
                    position: 0,
                    config: {}
                }]
            };
        },

        onChangeSequence() {
            // here, we create a new sequence with repositoryFactory
            const sequence = {
                parentId: null,
                ruleId: null,
                actionName: null,
                position: this.getParentSequences.length + 1,
                config: {}
            };

            this.flow.flowSequences = [...this.flow.flowSequences, sequence];
        },

        getSequenceByPosition(position) {
            return this.flow.flowSequences.filter(item => item.position === position);
        },

        getStyleGrid(position) {
            const columns = (this.flow?.flowSequences || [])
                .filter(item => item.position === position && item.ruleId !== null);

            return {
                display: 'grid',
                'grid-auto-rows': 'min-content',
                'grid-auto-flow': 'dense',
                'grid-template-columns': columns.length > 1
                    ? `repeat(${columns.length}, minmax(324px, 1fr))`
                    : '1fr auto',
                gap: '72px'
            };
        }
    }
});
