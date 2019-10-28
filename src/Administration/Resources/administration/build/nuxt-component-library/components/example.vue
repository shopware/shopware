<template>
    <div class="example" :class="{ 'is--fullpage-panel': component.meta.exampleType === 'static' }">
        <div class="dynamic-renderer-panel panel">
            <div class="panel--headline">
                <span v-if="component.meta.exampleType === 'dynamic'" >
                    Interactive component preview
                </span>
                <span v-else-if="component.meta.exampleType === 'static'">
                    Static component preview
                </span>
            </div>
            <div class="panel--content">
                <dynamic-renderer v-if="component.meta.exampleType === 'dynamic'" class="dynamic-renderer" :component="component" :settingsProps="settingsProps" @source-changed="onSourceChanged"></dynamic-renderer>
                <static-renderer v-else-if="component.meta.exampleType === 'static'" :example="component.meta.example"></static-renderer>
            </div>
        </div>

        <form class="settings panel" @change="onChange($event)" @submit.prevent ref="settingsForm" v-if="component.meta.exampleType === 'dynamic'">
            <div class="panel--headline">
                Component settings
            </div>
            <div class="panel--content">
                <div v-for="prop in initialProps">
                    <label v-if="prop.type === 'String' || prop.type === 'Number' || prop.type === 'Boolean'">
                        <span class="label--title">{{ prop.key }}<span v-if="prop.required == true">*</span>:</span>
                        <input v-if="prop.type === 'String' && !prop.validValues" type="text" v-model="settingsProps.variables[prop.key].value" :name="prop.key" autocomplete="false">
                        <input v-if="prop.type === 'Number'" type="number" v-model="settingsProps.variables[prop.key].value" :name="prop.key" autocomplete="false">
                        <select v-if="prop.type === 'String' && prop.validValues" type="text" v-model="settingsProps.variables[prop.key].value" :name="prop.key">
                            <option value="">(default)</option>
                            <option :value="val.value" v-for="(val, index) in prop.validValues" :key="index">{{ val.display }}</option>
                        </select>
                        <input type="checkbox" v-else-if="prop.type === 'Boolean'" v-model="settingsProps.variables[prop.key].value" :name="prop.key" />
                    </label>
                </div>
            </div>

            <template v-if="component.slots && Object.keys(component.slots).length">
                <div class="panel--headline">
                    Slots
                </div>

                <div class="panel--content">
                    <div class="slot--row" v-for="(slot, index) in component.slots" :key="index">
                        <label>
                            <span class="label--title">{{ slot.isDefault ? 'default': slot.name }}:</span>
                            <textarea :name="slot.isDefault ? 'default': slot.name" v-model="settingsProps.slots[slot.isDefault ? 'default': slot.name]"></textarea>
                        </label>
                    </div>
                </div>
            </template>
        </form>
    </div>
</template>

<style lang="scss" scoped>
    .example {
        display: grid;
        grid-template-columns: 60% 40%;
        background: #fafbfc;
        border: 1px solid #d8dde6;

        &.is--fullpage-panel {
            grid-template-columns: 100%;
        }
    }

    .panel {
        .panel--headline {
            background: #fff;
            color: #000;
            font-weight: bold;
            border-bottom: 1px solid #d8dde6;
            padding: 10px 16px;
            box-shadow: 0 10px 10px -10px rgba(0, 0, 0, 0.15);

            &:not(:first-child) {
                border-top: 1px solid #d8dde6;
            }
        }

        .panel--content {
            position: relative;
            padding: 20px;
            overflow: hidden;
        }
    }

    .dynamic-renderer-panel {
        display: grid;
        grid-template-rows: auto 1fr;

        .panel--content {
            padding: 50px;
        }
    }

    .settings {
        border-left: 1px solid #d8dde6;

        label {
            display: block;
            margin-bottom: 8px;
        }
        .label--title {
            display: block;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 4px;
        }

        input:not([type="checkbox"]), textarea {
            display: block;
            background: #fff;
            border: 1px solid #d8dde6;
            border-radius: 4px;
            width: 100%;
            padding: 12px 8px;
            color: #000;

            &:focus {
                outline: none;
                border-color: #189eff;
                box-shadow: 0 0 4px #b1deff;
            }
        }

        select {
            width: 100%;
        }

        textarea {
            height: 75px;
            min-width: 100%;
            max-width: 100%;
            min-height: 75px;
            max-height: 300px;
        }
    }

</style>

<script>
    import dynamicRenderer from '~/components/dynamic-renderer';
    import staticRenderer from '~/components/static-renderer';

    export default {
        props: {
            component: {
                type: Object,
                required: true
            }
        },
        components: {
            'dynamic-renderer': dynamicRenderer,
            'static-renderer': staticRenderer
        },

        data() {
            return {
                initialProps: this.component.props,
                settingsProps: {
                    variables: {},
                    slots: {
                        default: 'Lorem ipsum dolor sit amet'
                    }
                }
            };
        },

        created() {
            this.settingsProps.variables = this.initialProps.reduce((accumulator, entry) => {
                return { ...accumulator, ...{
                    [entry.key]: {
                        type: entry.type,
                        value: entry.default
                    }
                } };
            }, {});
        },

        methods: {
            onChange() {
                this.updatedProps = Array.from(
                    new FormData(this.$refs.settingsForm).entries()
                ).reduce((accumulator, entry) => {
                    const [ key, value ] = entry;
                    return {
                        ...accumulator, ...{
                            [key]: value
                        }
                    };
                }, {});
            },

            onSourceChanged({ settings }) {
                const variables = settings.variables;
                const slots = settings.slots;
                let html = `<${this.component.name}`;

                const variablesString = Object.keys(settings.variables).reduce((accumulator, variableKey) => {
                    const variableOpts = variables[variableKey];
                    const type = variableOpts.type.toLowerCase();
                    const value = variableOpts.value;

                    if (type === 'string' && value && value.length > 1) {
                        accumulator += ` ${variableKey}="${value}"`
                    } else if (type !== 'string' && value !== undefined) {
                        accumulator += ` :${variableKey}="${variableOpts.value}"`
                    }

                    return accumulator;
                }, '');

                const slotsString = Object.keys(slots).reduce((accumulator, slotKey) => {
                    const slotContent = slots[slotKey];

                    let slotDefinition = this.component.slots.filter((slot) => {
                        return slot.name === slotKey;
                    });

                    if (slotDefinition && slotDefinition.length) {
                        slotDefinition = slotDefinition[0];
                    }

                    if (slotKey === 'default') {
                        accumulator += `\n    ${slotContent}\n`;
                    } else {
                        accumulator += `\n    <template v-slot:${slotKey}${slotDefinition.variables.length ? '="{ ' + slotDefinition.variables.join(', ') + ' }"' : ''}>${slotContent}</template>\n`;
                    }

                    return accumulator;
                }, '');

                html = `${html}${variablesString}>${slotsString}</${this.component.name}>`;

                this.$emit('source-changed', html);
            }
        }
    }
</script>
