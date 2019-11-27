import template from './sw-property-detail-base.html.twig';

const { Component, Mixin } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

Component.register('sw-property-detail-base', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        propertyGroup: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        isLoading: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {
            sortingTypes: [
                { value: 'numeric', label: this.$tc('sw-property.detail.numericSortingType') },
                { value: 'alphanumeric', label: this.$tc('sw-property.detail.alphanumericSortingType') },
                { value: 'position', label: this.$tc('sw-property.detail.positionSortingType') }
            ],
            displayTypes: [
                { value: 'media', label: this.$tc('sw-property.detail.mediaDisplayType') },
                { value: 'text', label: this.$tc('sw-property.detail.textDisplayType') },
                { value: 'color', label: this.$tc('sw-property.detail.colorDisplayType') }
            ]
        };
    },

    computed: {
        ...mapApiErrors('propertyGroup', [
            'name',
            'displayType',
            'sortingType'
        ])
    }
});
