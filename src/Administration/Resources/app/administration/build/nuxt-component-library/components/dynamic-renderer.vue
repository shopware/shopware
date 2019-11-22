<script>
export default {
    name: 'dynamic-renderer',
    props: {
        component: {
            type: Object,
            required: true
        },
        settingsProps: {
            type: Object,
            required: true
        }
    },

    render(h) {
        const componentName = this.component.name;
        const variableProps = Object.keys(this.settingsProps.variables).reduce((accumulator, key) => {
            const item = this.settingsProps.variables[key];
            if (!accumulator[key]) {
                let value = item.value;

                if (!item.type) {
                    item.type = 'String';
                }

                // Cast the values
                if (item.type.toLowerCase() === 'number' && value) {
                    value = parseFloat(value);
                } else if (item.type.toLowerCase() === 'boolean') {
                    value = item.value;
                } else if (item.type.toLowerCase() === 'string') {
                    value = item.value;
                } else {
                    value = undefined;
                }

                accumulator[key] = value;
            }
            return accumulator;
        }, {});

        const slots = Object.keys(this.settingsProps.slots).reduce((accumulator, key) => {
            const item = this.settingsProps.slots[key];
            if (!accumulator[key]) {
                accumulator[key] = item;
            }
            return accumulator;
        }, {});

        const scopedSlots = Object.keys(slots).reduce((accumulator, slotName) => {
            const slotContent = slots[ slotName ];
            accumulator[slotName] = (props) => {
                return h('div', {
                    props: props,
                    domProps: { innerHTML: slotContent }
                });
            };
            return accumulator
        }, {});

        this.$emit('source-changed', {
            props: variableProps,
            slots: scopedSlots,
            settings: this.settingsProps
        });

        return h(componentName, {
            props: variableProps,
            scopedSlots
        });
    }
}
</script>