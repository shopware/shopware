<template>
    <div class="page--component">
        <h1>{{ componentTitle }} <div :class="tipClass">{{ component.meta.status }}</div></h1>

        <p class="is--xl" v-if="component.meta.description" v-html="component.meta.description"></p>
    
        <section class="section--usage" v-if="component.meta.example">
            <h3>Usage</h3>

            <div class="live-demo">
                <div class="live-demo--example"  v-if="component.meta.exampleType !== 'code-only'">
                    <no-ssr placeholder="Loading...">
                        <example :component="component" @source-changed="onSourceChanged"></example>
                    </no-ssr>
                </div>

                <div class="live-demo--code">
                    <prism language="html">{{ codeExample }}</prism>
                </div>
            </div>
        </section>

        <section class="section--component-properties" v-if="component.props.length">
            <h3>Component properties</h3>
            <table>
                <thead>
                    <tr>
                        <th>Property name</th>
                        <th>Type</th>
                        <th>Default</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="prop in component.props" :key="prop.key">
                        <td>
                            <u>{{ prop.key }}</u>
                        </td>

                        <td>
                            {{ prop.type }}
                        </td>

                        <td>
                            {{ prop.default }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="section--less-variables" v-if="component.lessVariables.length">
            <h3>Less variables</h3>
            <table>
                <thead>
                    <tr>
                        <th>Variable name</th>
                        <th>Value</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="variable in component.lessVariables" :key="variable.key">
                        <td>
                            <u>{{ variable.key }}</u>
                        </td>

                        <td>
                            {{ variable.value }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>

<script>
import Prism from 'vue-prism-component';
import 'prismjs/themes/prism-okaidia.css';
import exampleComponent from '~/components/example';

export default {
    components: {
        Prism,
        'example': exampleComponent
    },

    data() {
        return {
            title: '',
            codeExample: '',
            component: {
                blocks: [],
                computed: [],
                hooks: [],
                imports: [],
                inject: [],
                lessVariables: [],
                methods: [],
                mixins: [],
                name: '',
                props: [],
                slots: [],
                watcher: [],
                meta: {
                    tags: {
                        'component-example': '',
                        status: ''
                    }
                }
            }
        };
    },

    head() {
        return {
            title: this.componentTitle
        };
    },

    computed: {
        componentTitle() {
            return `<${this.title}>`;
        },
        tipClass() {
            const status = this.component.meta.status;
            if (status === 'deprecated') {
                return {
                    tip: true
                };
            }

            if (status === 'prototype') {
                return {
                    tip: true,
                    'is--flag': true
                };
            }

            if (status === 'ready') {
                return {
                    tip: true,
                    'is--success': true
                };
            }

            return {
                tip: true,
                'is--flag': true
            };
        }
    },
    
    created() {
        this.getComponentTitleFromRoute();
        const component = this.findComponent(this.title);
        if (component) {
            this.component = component;
            this.codeExample = component.meta.example
        }
    },

    beforeRouteUpdate(to, from, next) {
        this.getComponentTitleFromRoute();
        const component = this.findComponent(this.title);
        if (component) {
            this.component = component;
        }
        next();
    },

    methods: {
        findComponent(name) {
            const component = this.$filesInfo.reduce((accumulator, item) => {
                if (item.source.name === name) {
                    accumulator = item.source;
                }
                return accumulator;
            }, null);
            return component;
        },

        getComponentTitleFromRoute() {
            const title = this.$route.params.name;
            this.title = title;
        },

        onSourceChanged(componentHTML) {
            this.codeExample = componentHTML;
        }
    },
}
</script>


