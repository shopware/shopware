import utils from 'src/core/service/util.service';
import template from './sw-condition-tree.html.twig';

const AND_CONTAINER_NAME = 'andContainer';
const OR_CONTAINER_NAME = 'orContainer';

export default {
    name: 'sw-condition-tree',
    template,

    data() {
        return {
            nestedConditions: {},
            entityAssociationStore: {}
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
        conditionIdentifier: {
            type: String,
            required: true
        },
        entityName: {
            type: String,
            required: true
        }
    },
    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.entityAssociationStore = this.entity.getAssociation(this.conditionIdentifier);
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
                        if (current.children.indexOf(child) === -1) {
                            current.children.push(child);
                        }
                    });

                    accumulator.push(current);
                }

                return accumulator;
            }, []);
        },

        // todo: standardized container
        checkRootContainer(nestedConditions) {
            if (nestedConditions.length === 1
                && nestedConditions[0].type === OR_CONTAINER_NAME) {
                if (nestedConditions[0].children.length > 0) {
                    return nestedConditions[0];
                }

                nestedConditions[0].children = [
                    this.createCondition(
                        AND_CONTAINER_NAME,
                        nestedConditions[0].id
                    )
                ];

                return nestedConditions[0];
            }

            const rootCondition = this.createCondition(OR_CONTAINER_NAME, null);
            const subCondition = this.createCondition(
                AND_CONTAINER_NAME,
                rootCondition.id,
                nestedConditions
            );
            rootCondition.children = [subCondition];

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

        createCondition(type, parentId, children = null) {
            const conditionId = utils.createId();
            const conditionData = {
                type: type,
                parentId: parentId
            };

            if (children) {
                children.forEach((child) => {
                    child.parentId = conditionId;
                });
                conditionData.children = children;
            }

            return Object.assign(this.entityAssociationStore.create(conditionId), conditionData);
        }
    }
};
