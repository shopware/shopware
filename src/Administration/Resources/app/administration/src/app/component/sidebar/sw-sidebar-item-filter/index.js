import template from './sw-sidebar-item-filter.html.twig';
import './sw-sidebar-item-filter.scss';

const {Component, Mixin, Context} = Shopware;
const {Criteria, EntityCollection} = Shopware.Data;

const filterInputTypeOptions = {
    switch: 'switch',
    range: 'range',
    input: 'input',
    number: 'number',
    singleSelect: 'singleSelect',
    multiSelect: 'multiSelect'
};

Component.register('sw-sidebar-item-filter', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isLoading: false,
            filter: {},
            selectedOptions: {},
            repository: {}
        };
    },

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        filterOptions: {
            type: Array,
            required: false,
            default: [
                {
                    name: 'activeInactive',
                    label: 'activeInactive',
                    placeholder: 'activeInactive',
                    field: 'product.active',
                    inputType: 'singleSelect',
                    criteriaType: 'equals',
                    options: [
                        {
                            name: 'All',
                            value: null
                        },
                        {
                            name: 'Active',
                            value: true
                        },
                        {
                            name: 'Inactive',
                            value: false
                        }
                    ]
                }
            ],
            schema: [{
                name: 'camelCase, unique',
                label: 'label for input',
                placeholder: 'placeholder for input',
                field: 'field for query e.g. product.active',
                inputType: 'one of filterInputTypeOptions',
                criteriaType: 'Criteria operator e.g. equals',
                options: [
                    {
                        name: 'option name',
                        value: 'unique id'
                    },
                    {
                        name: 'option name',
                        value: 'unique id'
                    }
                ],
                repository: 'repository reference used to with repositoryFactory.create - this is used to populate options'
            }]
        }
    },

    watch: {
        filter: {
            handler() {
                this.$emit('update-criteria-array', this.getFilterCriteria());
            },
            deep: true
        }
    },

    async created() {
        this.setRepositoriesAndNestedVariables();

        await this.setOptionsOnFilters();
    },

    methods: {
        inputTrigger(event, filterOptionName) {
            this.$set(this.filter, filterOptionName, event);
        },

        setRepositoriesAndNestedVariables() {
            this.filterOptions.forEach((filterOption) => {
                switch (filterOption.property.type) {
                    case "association":
                        filterOption.inputType = 'multiSelect'
                        filterOption.criteriaType = 'equalsAny'

                        break;
                    case "boolean":
                        filterOption.inputType = 'singleSelect';
                        filterOption.criteriaType = 'equals';

                        filterOption.options = [
                            {
                                name: 'All',
                                value: null
                            },
                            {
                                name: 'True',
                                value: true
                            },
                            {
                                name: 'False',
                                value: false
                            }
                        ]

                        break;
                    case "string":
                        filterOption.inputType = 'input';
                        filterOption.criteriaType = 'contains';

                        break;
                    case "int":
                        filterOption.inputType = 'range';
                        filterOption.criteriaType = 'range';

                        break;
                }

                if (filterOption.property.entity) {
                    filterOption.entity = filterOption.property.entity

                    const entityRepository = this.repositoryFactory.create(filterOption.entity);

                    this.repository[filterOption.entity] = entityRepository;

                    filterOption.entityCollection = new EntityCollection(
                        entityRepository.route,
                        entityRepository.entityName,
                        Context.api
                    );
                }

                if (filterOption.inputType === filterInputTypeOptions.range) {
                    this.$set(this.filter, filterOption.key, {from: null, to: null});
                }
            });
        },

        rangeInputTrigger(event, filterOptionName, criteria) {
            const value = this.filter[filterOptionName];
            value[criteria] = event;

            this.$set(this.filter, filterOptionName, value);
        },

        setOptionsOnFilters() {
            this.loading = true;
            const promises = [];
            this.filterOptions.forEach((filterOption) => {
                if (filterOption.entity) {
                    promises.push(this.repository[filterOption.entity].search(
                        new Criteria(),
                        Shopware.Context.api
                    ).then((response) => {
                        response = response.map((object) => {
                            return {
                                name: object.name || object.title,
                                value: object.id
                            };
                        });

                        filterOption.options = response;
                    }));
                }
            });
            return Promise.all(promises).then(() => {
                this.loading = false;
                this.$forceUpdate();
            });
        },

        getFilterCriteria() {
            const filterCriterias = [];

            this.filterOptions.forEach((filterOption) => {
                if (!filterInputTypeOptions[filterOption.inputType]) {
                    this.createNotificationError({
                        title: `Unknown type ${filterOption.inputType}`,
                        message: `Unknown type ${filterOption.inputType} for ${JSON.parse(filterOption)}`
                    });
                }

                const {field} = filterOption;

                let value;
                if (filterOption.criteriaType === filterInputTypeOptions.range) {
                    value = {};
                    if (this.filter[filterOption.key].from || this.filter[filterOption.key].from === 0) {
                        value.gte = this.filter[filterOption.key].from;
                    }
                    if (this.filter[filterOption.key].to || this.filter[filterOption.key].to === 0) {
                        value.lte = this.filter[filterOption.key].to;
                    }
                    if (!value.gte && value.gte !== 0 && !value.lte && value.lte !== 0) return;
                } else {
                    value = this.filter[filterOption.key];
                }

                if ((typeof value === 'undefined') || value === null || value.length === 0) return;

                try {
                    filterCriterias.push(Criteria[filterOption.criteriaType](field, value));
                } catch (error) {
                    this.createNotificationError({
                        title: `Unknown criteriaType ${filterOption.criteriaType} for ${filterOption}`,
                        message: error
                    });
                }
            });

            if (filterCriterias.length) {
                return Criteria.multi(
                    'AND',
                    filterCriterias
                )
            }
            return false;
        },

        getManufacturerList() {
            const criteria = new Criteria();
            criteria.setLimit(500);
            this.manufacturerRepository.search(criteria, Shopware.Context.api).then(response => {
                let manufacturers = response;

                manufacturers = manufacturers.map((manufacturer) => {
                    return {
                        id: manufacturer.id,
                        name: manufacturer.name
                    };
                });

                this.manufacturers = manufacturers;
            });
        },

        getSalesChannelList() {
            const criteria = new Criteria();
            criteria.setLimit(500);
            this.salesChannelRepository.search(criteria, Shopware.Context.api).then(response => {
                let salesChannels = response;

                salesChannels = salesChannels.map((salesChannel) => {
                    return {
                        id: salesChannel.id,
                        name: salesChannel.name
                    };
                });

                this.salesChannels = salesChannels;
            });
        }
    }
});