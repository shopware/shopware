import utils from 'src/core/service/util.service';
import template from './sw-condition-tree.html.twig';

export default {
    name: 'sw-condition-tree',
    template,

    data() {
        return {
            nestedConditions: {},
            entityAssociationStore: {},
            isApi: false
        };
    },

    provide() {
        return {
            conditionStore: this.conditionStore,
            entityAssociationStore: () => this.entityAssociationStore,
            config: this.config
        };
    },

    props: {
        entity: {
            type: Object,
            required: true
        },
        conditionStore: {
            type: Object,
            required: true
        },
        config: {
            type: Object,
            required: true
        }
    },
    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.entityAssociationStore = this.entity.getAssociation(this.config.conditionIdentifier);
            this.entityAssociationStore.getList({
                page: 1,
                limit: 500,
                sortBy: 'position'
            }).then((conditionCollection) => {
                this.nestedConditions = this.checkRootContainer(this.buildNestedConditions(conditionCollection.items, null));
            });
        },

        buildNestedConditions(conditions, parentId) {
            return conditions.reduce((accumulator, current) => {
                if (current.parentId === parentId) {
                    const children = this.buildNestedConditions(conditions, current.id);
                    children.forEach((child) => {
                        if (current[this.config.childName].indexOf(child) === -1) {
                            current[this.config.childName].push(child);
                        }
                    });

                    accumulator.push(current);
                }

                return accumulator;
            }, []);
        },

        checkRootContainer(nestedConditions) {
            if (nestedConditions.length === 1
                && this.config.isOrContainer(nestedConditions[0])) {
                if (nestedConditions[0][this.config.childName].length > 0) {
                    return nestedConditions[0];
                }

                nestedConditions[0][this.config.childName] = [
                    this.createCondition(
                        this.config.andContainer,
                        nestedConditions[0].id
                    )
                ];

                return nestedConditions[0];
            }

            const rootCondition = this.createCondition(this.config.orContainer, null);
            const subCondition = this.createCondition(
                this.config.andContainer,
                rootCondition.id,
                nestedConditions
            );
            rootCondition[this.config.childName] = [subCondition];

            if (!nestedConditions.length) {
                return rootCondition;
            }

            this.entityAssociationStore.removeById(rootCondition.id);
            this.entityAssociationStore.removeById(subCondition.id);
            this.entityAssociationStore.store = Object.assign(
                { [rootCondition.id]: rootCondition },
                { [subCondition.id]: subCondition },
                this.entityAssociationStore.store
            );

            return rootCondition;
        },

        createCondition(conditionData, parentId, children) {
            const conditionId = utils.createId();
            const condition = Object.assign(this.entityAssociationStore.create(conditionId), conditionData);
            condition.parentId = parentId;

            if (children) {
                children.forEach((child) => {
                    child.parentId = conditionId;
                });
                condition[this.config.childName] = children;
            }

            return condition;
        }
    }
};
