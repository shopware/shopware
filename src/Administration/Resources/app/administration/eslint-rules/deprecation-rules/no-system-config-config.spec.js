/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-system-config-config');
const data = require('./no-system-config-config.data');

const member = (name) => data.members.find((entry) => entry.name === name).message;
const CALL = data.calls[0].message;
const DECORATOR = data.decorator.message;
const ROUTE = data.literals[0].message;
const scriptTodo = (text) => `// TODO Codemod(6.8, system-config-schema): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, system-config-schema): ${text} #}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-system-config-config (script)', rule, {
    valid: [
        { name: 'ignores getSchema', code: "const tabs = await this.systemConfigApiService.getSchema('AcmePlugin.config');" },
        { name: 'ignores getConfig of other services', code: "const config = await this.configService.getConfig();\nShopware.Service('cmsService').getConfig();" },
        { name: 'ignores config in other components', code: "Component.override('sw-cms-el-config-text', { data() { return { config: {} }; } });" },
        { name: 'ignores a computed config of sw-system-config overrides', code: "Component.override('sw-system-config', { computed: { config() { return {}; } } });" },
        {
            name: 'ignores decorators of other methods',
            code: "Shopware.Application.addServiceProviderDecorator('systemConfigApiService', (service) => {\n    service.batchSave = () => {};\n    return service;\n});",
        },
        { name: 'ignores the get-schema route', code: "const route = '_action/system-config/get-schema';" },
    ],
    invalid: [
        {
            name: 'marks the service call',
            code: "const cards = await this.systemConfigApiService.getConfig('AcmePlugin.config');",
            output: [scriptTodo(CALL), "const cards = await this.systemConfigApiService.getConfig('AcmePlugin.config');"].join('\n'),
            errors: [{ message: CALL }],
        },
        {
            name: 'marks decorators that replace getConfig',
            code: [
                'const { Application } = Shopware;',
                "Application.addServiceProviderDecorator('systemConfigApiService', (service) => {",
                '    const original = service.getConfig.bind(service);',
                '    service.getConfig = async (domain) => [...(await original(domain)), acmeCard];',
                '    return service;',
                '});',
            ].join('\n'),
            output: [
                'const { Application } = Shopware;',
                "Application.addServiceProviderDecorator('systemConfigApiService', (service) => {",
                '    const original = service.getConfig.bind(service);',
                `    ${scriptTodo(DECORATOR)}`,
                '    service.getConfig = async (domain) => [...(await original(domain)), acmeCard];',
                '    return service;',
                '});',
            ].join('\n'),
            errors: [{ message: DECORATOR }],
        },
        {
            name: 'marks a direct replacement of getConfig',
            code: "Shopware.Service('systemConfigApiService').getConfig = acmeGetConfig;",
            output: [scriptTodo(DECORATOR), "Shopware.Service('systemConfigApiService').getConfig = acmeGetConfig;"].join('\n'),
            errors: [{ message: DECORATOR }],
        },
        {
            name: 'marks the members inside overrides of sw-system-config',
            code: [
                "Component.override('sw-system-config', {",
                '    watch: {',
                '        config() {',
                '            this.acmeSync();',
                '        },',
                '    },',
                '    methods: {',
                '        async readConfig() {',
                '            this.schema = await this.getSchemaForDomain();',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-system-config', {",
                '    watch: {',
                `        ${scriptTodo(member('config'))}`,
                '        config() {',
                '            this.acmeSync();',
                '        },',
                '    },',
                '    methods: {',
                '        async readConfig() {',
                `            ${scriptTodo(member('getSchemaForDomain'))}`,
                '            this.schema = await this.getSchemaForDomain();',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: member('config') }, { message: member('getSchemaForDomain') }],
        },
        {
            name: 'marks the route',
            code: 'const route = `/api/_action/system-config/schema?domain=${domain}`;',
            output: [scriptTodo(ROUTE), 'const route = `/api/_action/system-config/schema?domain=${domain}`;'].join('\n'),
            errors: [{ message: ROUTE }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-system-config-config (template)', rule, {
    valid: [
        {
            name: 'ignores slot props named config',
            filename: '/plugin/src/acme-config/acme-config.html.twig',
            code: [
                '<template>',
                '    <sw-system-config domain="AcmePlugin.config">',
                '        <template #card-element="{ element, config }">',
                '            {{ config[element.name] }}',
                '        </template>',
                '    </sw-system-config>',
                '</template>',
            ].join('\n'),
        },
        {
            name: 'ignores the config key of slot bindings in overrides',
            filename: '/plugin/src/sw-system-config/sw-system-config.html.twig',
            code: [
                '<template>',
                '    {% block sw_system_config_content_card %}',
                '    <slot name="beforeElements" v-bind="{ card, config: actualConfigData[currentSalesChannelId] }"></slot>',
                '    {% endblock %}',
                '</template>',
            ].join('\n'),
        },
    ],
    invalid: [
        {
            name: 'marks the config loop variable in overrides of sw_system_config_content_card',
            filename: '/plugin/src/sw-system-config/sw-system-config.html.twig',
            code: [
                '<template>',
                '    {% block sw_system_config_content_card %}',
                '    <mt-card v-for="card in config" :key="card.name" />',
                '    {% endblock %}',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    {% block sw_system_config_content_card %}',
                `    ${twigTodo(member('config'))}`,
                '    <mt-card v-for="card in config" :key="card.name" />',
                '    {% endblock %}',
                '</template>',
            ].join('\n'),
            errors: [{ message: member('config') }],
        },
    ],
});
