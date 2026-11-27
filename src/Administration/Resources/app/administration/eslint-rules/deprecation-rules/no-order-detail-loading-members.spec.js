/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-order-detail-loading-members');
const data = require('./no-order-detail-loading-members.data');

const message = (component, name) => data.members.find((entry) => entry.name === name && (entry.unique || entry.component === component)).message;
const eventMessage = (name) => data.events.find((entry) => entry.name === name).message;
const scriptTodo = (text) => `// TODO Codemod(6.8, order-detail-store): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, order-detail-store): ${text} #}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-order-detail-loading-members (script)', rule, {
    valid: [
        { name: 'ignores the getters of the swOrderDetail store', code: "const busy = Shopware.Store.get('swOrderDetail').isLoading || Shopware.Store.get('swOrderDetail').isEditing;" },
        { name: 'ignores isLoading of other components', code: "Component.override('sw-order-list', { computed: { isLoading() { return this.isLoading; } } });" },
        { name: 'ignores isSaveSuccessful of sw-order-detail, which stays', code: "Component.override('sw-order-detail', { computed: { isSaveSuccessful() { return this.isSaveSuccessful; } } });" },
        { name: 'ignores loading-change of other components', code: "Component.override('sw-users-permissions', { methods: { a() { this.$emit('loading-change', true); } } });" },
        { name: 'ignores the store action setLoading', code: "Shopware.Store.get('swOrderDetail').setLoading(['order', true]);" },
    ],
    invalid: [
        {
            name: 'marks an injected provide in a view override',
            code: [
                "Shopware.Component.override('sw-order-detail-general', {",
                "    inject: ['swOrderDetailOnLoadingChange'],",
                '});',
            ].join('\n'),
            output: [
                "Shopware.Component.override('sw-order-detail-general', {",
                `    ${scriptTodo(message(null, 'swOrderDetailOnLoadingChange'))}`,
                "    inject: ['swOrderDetailOnLoadingChange'],",
                '});',
            ].join('\n'),
            errors: [{ message: message(null, 'swOrderDetailOnLoadingChange') }],
        },
        {
            name: 'marks generic members inside overrides of sw-order-detail',
            code: [
                "Component.override('sw-order-detail', {",
                '    methods: {',
                '        onUpdateLoading(value) {',
                '            this.isEditing = value;',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-order-detail', {",
                '    methods: {',
                `        ${scriptTodo(message('sw-order-detail', 'onUpdateLoading'))}`,
                '        onUpdateLoading(value) {',
                `            ${scriptTodo(message('sw-order-detail', 'isEditing'))}`,
                '            this.isEditing = value;',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: message('sw-order-detail', 'onUpdateLoading') }, { message: message('sw-order-detail', 'isEditing') }],
        },
        {
            name: 'marks the props and computeds of the views',
            code: [
                "Component.override('sw-order-detail-details', {",
                '    computed: {',
                '        busy() {',
                '            return this.isLoading',
                '                || this.isSaveSuccessful;',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-order-detail-details', {",
                '    computed: {',
                '        busy() {',
                `            ${scriptTodo(message('sw-order-detail-details', 'isLoading'))}`,
                '            return this.isLoading',
                `                ${scriptTodo(message('sw-order-detail-details', 'isSaveSuccessful'))}`,
                '                || this.isSaveSuccessful;',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: message('sw-order-detail-details', 'isLoading') }, { message: message('sw-order-detail-details', 'isSaveSuccessful') }],
        },
        {
            name: 'marks unique names anywhere',
            code: 'this.swOrderDetailOnEditingChange(true);',
            output: [scriptTodo(message(null, 'swOrderDetailOnEditingChange')), 'this.swOrderDetailOnEditingChange(true);'].join('\n'),
            errors: [{ message: message(null, 'swOrderDetailOnEditingChange') }],
        },
        {
            name: 'marks events emitted to sw-order-detail from its views',
            code: "Component.override('sw-order-detail-general', { methods: { a() { this.$emit('loading-change', true); } } });",
            output: [scriptTodo(eventMessage('loading-change')), "Component.override('sw-order-detail-general', { methods: { a() { this.$emit('loading-change', true); } } });"].join('\n'),
            errors: [{ message: eventMessage('loading-change') }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-order-detail-loading-members (template)', rule, {
    valid: [
        {
            name: 'ignores the router-view attributes that stay',
            filename: '/plugin/src/sw-order-detail/sw-order-detail.html.twig',
            code: '<template>\n    <router-view :is-loading="loading.order" @save-edits="onSaveEdits" />\n</template>',
        },
        {
            name: 'ignores router-view in templates of other components',
            filename: '/plugin/src/sw-users-permissions/sw-users-permissions.html.twig',
            code: '<template>\n    <router-view @loading-change="onLoading" />\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks a router-view with removed attributes in an override of sw-order-detail',
            filename: '/plugin/src/sw-order-detail/sw-order-detail.html.twig',
            code: [
                '<template>',
                '    <router-view',
                '        :is-save-successful="isSaveSuccessful"',
                '        @loading-change="acmeOnLoading"',
                '    />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                `    ${twigTodo(data.routerView.message)}`,
                '    <router-view',
                '        :is-save-successful="isSaveSuccessful"',
                '        @loading-change="acmeOnLoading"',
                '    />',
                '</template>',
            ].join('\n'),
            errors: [{ message: data.routerView.message }],
        },
        {
            name: 'marks generic members in templates of the views',
            filename: '/plugin/src/sw-order-detail-general/sw-order-detail-general.html.twig',
            code: '<template>\n    <sw-order-promotion-field @loading-change="updateLoading" />\n</template>',
            output: [
                '<template>',
                `    ${twigTodo(message('sw-order-detail-general', 'updateLoading'))}`,
                '    <sw-order-promotion-field @loading-change="updateLoading" />',
                '</template>',
            ].join('\n'),
            errors: [{ message: message('sw-order-detail-general', 'updateLoading') }],
        },
    ],
});
