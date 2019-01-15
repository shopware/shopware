import utils from 'src/core/service/util.service';
import template from './sw-condition-tree.html.twig';
import './sw-condition-base';
import './sw-condition-and-container';

const AND_CONTAINER_NAME = 'andContainer';
const OR_CONTAINER_NAME = 'orContainer';


export default {
    name: 'sw-condition-tree',
    template,

    data() {
        return {
            nestedConditions: {},
            conditionAssociations: {},
            entity: {}
        };
    },

    props: {
        store: {
            type: Object,
            required: true
        },
        conditionStore: {
            type: Object,
            required: true
        }
    },
    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.id) {
                return;
            }

            this.entity = this.store.getById(this.$route.params.id);

            this.conditionAssociations = this.entity.getAssociation('conditions');
            this.conditionAssociations.getList({
                page: 1,
                limit: 500,
                sortBy: 'position'
            }).then(() => {
                this.nestedConditions = this.buildNestedConditions(this.entity.conditions, null);

                this.$nextTick(() => {
                    this.$refs.mainContainer.$emit('finish-loading', this.nestedConditions);
                });
            });
        },

        buildNestedConditions(conditions, parentId) {
            const nestedConditions = conditions.reduce((accumulator, current) => {
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

            if (parentId !== null) {
                return nestedConditions;
            }

            return this.checkRootContainer(nestedConditions);
        },

        checkRootContainer(nestedConditions) {
            if (nestedConditions.length === 1
                && nestedConditions[0].type === OR_CONTAINER_NAME) {
                if (nestedConditions[0].children.length > 0) {
                    return nestedConditions[0];
                }

                nestedConditions[0].children = [
                    this.createCondition(
                        AND_CONTAINER_NAME,
                        utils.createId(),
                        nestedConditions[0].id
                    )
                ];

                return nestedConditions[0];
            }

            const rootId = utils.createId();
            const rootRole = this.createCondition(
                OR_CONTAINER_NAME,
                rootId
            );

            rootRole.children = [
                this.createCondition(
                    AND_CONTAINER_NAME,
                    utils.createId(),
                    rootRole.id,
                    nestedConditions
                )
            ];

            return rootRole;
        },

        createCondition(type, conditionId, parentId = null, children) {
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

            return Object.assign(this.conditionAssociations.create(conditionId), conditionData);
        }
    }
};
