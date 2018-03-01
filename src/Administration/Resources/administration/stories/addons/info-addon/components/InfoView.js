/**
 * Info panel vue component to render additional parameter. Please note we're using the render function of Vue.js
 * because Single File Components are not supported. The component will be parsed before the vue-loader is initialized,
 * therefore the only way around this issue was to use the render method.
 */
export default {
    name: 'SwagInfoView',
    props: {
        title: {
            type: String,
            required: true
        },
        template: {
            type: String,
            required: true
        },
        description: {
            type: String,
            default: ''
        },
        propsList: {
            type: Array,
            default: []
        },
        slots: {
            type: Array,
            default: []
        }
    },
    computed: {
        componentTitle() {
            return `<${this.title}>`;
        }
    },
    template: `
        <div class="swag-info-panel" style="padding: 30px">
            <h1 class="swag-info-panel__headline"><code>{{componentTitle}}</code></h1>
            
            <div class="swag-info-panel__description" v-html="description"></div>
            <div class="swag-info-panel__preview">
                <h2>Component preview</h2>
                
                <div class="swag-info-panel__preview-panel">
                    <slot name="default"></slot>
                </div>
            </div>
            
            <div class="swag-info-panel__usage-code">
                <h2>Usage example</h2>
                <pre class="language-html">
                    <code class="language-html" v-html="template"></code>
                </pre>
            </div>
            
            <div class="swag-info-panel__props" v-if="propsList.length">
                <h2>Properties list</h2>
                <table class="sw-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="prop in propsList">
                            <td><code>{{ prop.name }}</code></td>
                            <td><code>{{ prop.type }}</code></td>
                            <td><code>{{ prop.required }}</code></td>
                            <td><code>{{ prop.default }}</code></td>
                        </tr>
                    </tbody>
                </table> 
            </div>
            
            <div class="swag-info-panel__slots" v-if="slots.length">
                <h2>Available slots</h2>
                <table class="sw-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Tag</th>
                            <th>Scoped Slot</th>
                            <th>Slot Variable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="slot in slots">
                            <td><code>{{ slot.name }}</code></td>
                            <td><code><{{ slot.tag }}></code></td>
                            <td><code>{{ slot.scopedSlot }}</code></td>
                            <td><code>{{ slot.slotVariable }}</code></td>
                        </tr>
                    </tbody>
                </table> 
            </div>
        </div>
    
    `
};
