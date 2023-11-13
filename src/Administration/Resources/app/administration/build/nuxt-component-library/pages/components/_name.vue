<template>
    <div class="page--component">
        <deprecation-warning :component="component" />

        <header class="component--header">
            <h1 class="header--title">{{ componentTitle }}</h1>
            <h2 class="header--subtitle">
                {{ technicalTitle }}
                <span v-if="component.extendsFrom">
                    <span class="text--black">extends</span> &lt;{{ component.extendsFrom }}&gt;
                </span>
            </h2>
            <div :class="tipClass">{{ component.meta.status }}</div>
        </header>

        <div class="is--xl" v-if="component.meta.description" v-html="component.meta.description"></div>

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

        <section class="section--sass-variables" v-if="component.methods.length">
            <h3>Methods</h3>
            <table>
                <thead>
                <tr>
                    <th>Method name</th>
                    <th>Parameters</th>
                </tr>
                </thead>

                <tbody>
                <tr v-for="method in component.methods" :key="method.name">
                    <td>
                        <u>{{ method.name }}</u>
                    </td>

                    <td>
                        <span v-if="method.params.length > 0">
                            <u v-for="param in method.params" :key="param">
                                {{ param }}
                            </u>
                        </span>
                        <span v-else>
                            No parameters
                        </span>
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="section--imports" v-if="component.imports && component.imports.length">
            <h3>Imports</h3>

            <ul class="code-list">
                <li v-for="importName in component.imports" :key="importName">
                    <u>{{importName}}</u>
                </li>
            </ul>
        </section>

        <section class="section--imports" v-if="component.watcher && component.watcher.length">
            <h3>Watcher</h3>

            <ul class="code-list">
                <li v-for="watcher in component.watcher" :key="watcher">
                    <u>{{watcher}}</u>
                </li>
            </ul>
        </section>

        <section class="section--imports" v-if="component.mixins && component.mixins.length">
            <h3>Mixins</h3>

            <ul class="code-list">
                <li v-for="mixin in component.mixins" :key="mixin">
                    <u>{{mixin}}</u>
                </li>
            </ul>
        </section>

        <section class="section--imports" v-if="component.inject && component.inject.length">
            <h3>Inject</h3>

            <ul class="code-list">
                <li v-for="inject in component.inject" :key="inject">
                    <u>{{inject}}</u>
                </li>
            </ul>
        </section>

        <section class="section--sass-variables" v-if="component.sassVariables && component.sassVariables.length">
            <h3>SASS variables</h3>
            <table>
                <thead>
                <tr>
                    <th>Variable name</th>
                    <th>Value</th>
                </tr>
                </thead>

                <tbody>
                <tr v-for="variable in component.sassVariables" :key="variable.key">
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

        <section class="section--twig-blocks" v-if="component.blocks && component.blocks.length">
            <h3>Twig blocks</h3>

            <ul class="code-list">
                <li v-for="block in component.blocks" :key="block.name">
                    <u>{{block.name}}</u>
                </li>
            </ul>
        </section>

        <section class="section--vue-slots" v-if="component.slots && component.slots.length">
            <h3>Slots</h3>

            <ul class="code-list">
                <li v-for="slot in component.slots" :key="slot.name">
                    <u v-if="slot.name">{{slot.name}}</u>
                    <u v-else><i>(default)</i></u>
                </li>
            </ul>
        </section>
    </div>
</template>

<style lang="scss">
    .component--header {
        position: relative;

        .tip {
            position: absolute;
            right: 0;
            top: 15px;
        }
    }
    .header--title {
        margin: 0;
        display: inline-block;
    }

    .header--subtitle {
        margin: 0;
        display: inline-block;
        font-size: 1rem;
        color: #535c68;
    }

    .code-list {
        margin-left: 1em;

        li {
            margin-bottom: 8px;
        }

    }

    section {
        margin-top: 55px;
    }
</style>

<script>
import Prism from 'vue-prism-component';
import 'prismjs/themes/prism-okaidia.css';
import exampleComponent from '~/components/example';
import deprecationWarning from '~/components/deprecation-warning.vue';

export default {
    scrollToTop: true,

    components: {
        Prism,
        'example': exampleComponent,
        'deprecation-warning': deprecationWarning,
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
                methods: [],
                mixins: [],
                name: '',
                readableName: '',
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
            return `${this.component.readableName}`;
        },
        technicalTitle() {
            return `<${this.component.name}>`;
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


