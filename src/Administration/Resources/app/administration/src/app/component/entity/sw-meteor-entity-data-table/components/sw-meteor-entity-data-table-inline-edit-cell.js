/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export default {
    name: 'SwMeteorEntityDataTableInlineEditCell',

    props: {
        modelValue: {
            type: null,
            required: true,
        },
        isEditing: {
            type: Boolean,
            required: true,
        },
        isClickable: {
            type: Boolean,
            default: false,
        },
    },

    emits: [
        'update:modelValue',
        'open-detail',
        'start',
        'save',
        'cancel',
    ],

    methods: {
        onInput(event) {
            this.$emit('update:modelValue', event.target.value);
        },
    },

    template: `
        <span class="sw-meteor-entity-data-table-inline-edit-cell">
            <template v-if="isEditing">
                <input
                    class="sw-meteor-entity-data-table-inline-edit-cell__input"
                    :value="modelValue"
                    @input="onInput"
                >

                <button
                    class="sw-meteor-entity-data-table-inline-edit-cell__save"
                    type="button"
                    @click="$emit('save')"
                >
                    Save
                </button>

                <button
                    class="sw-meteor-entity-data-table-inline-edit-cell__cancel"
                    type="button"
                    @click="$emit('cancel')"
                >
                    Cancel
                </button>
            </template>

            <template v-else>
                <a
                    v-if="isClickable"
                    class="sw-meteor-entity-data-table-inline-edit-cell__value sw-meteor-entity-data-table-inline-edit-cell__value-link"
                    href="#"
                    @click.prevent="$emit('open-detail')"
                >
                    {{ modelValue }}
                </a>

                <span
                    v-else
                    class="sw-meteor-entity-data-table-inline-edit-cell__value"
                >
                    {{ modelValue }}
                </span>

                <button
                    class="sw-meteor-entity-data-table-inline-edit-cell__start"
                    type="button"
                    aria-label="Edit"
                    title="Edit"
                    @click="$emit('start')"
                >
                    <mt-icon
                        name="regular-pencil-s"
                        size="12px"
                    />
                </button>
            </template>
        </span>
    `,
};
