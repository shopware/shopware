/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { declaredMembers, thisMemberName, destructuredThisMembers, templateMemberReferences } = require('./member-usage');

const declaredRule = {
    meta: { type: 'problem', schema: [] },
    create(context) {
        return {
            'ExportDefaultDeclaration > ObjectExpression'(node) {
                const names = declaredMembers(node).map(({ kind, name }) => `${kind}.${name}`);

                context.report({ node, message: names.join(' ') });
            },
            MemberExpression(node) {
                const name = thisMemberName(node);

                if (name) {
                    context.report({ node, message: `this.${name}` });
                }
            },
            VariableDeclarator(node) {
                destructuredThisMembers(node).forEach(({ name }) => context.report({ node, message: `destructured.${name}` }));
            },
        };
    },
};

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('member-usage (script)', declaredRule, {
    valid: [],
    invalid: [
        {
            name: 'lists the members of every option kind',
            code: [
                'export default {',
                "    inject: ['repositoryFactory'],",
                '    props: { value: String },',
                "    emits: ['change'],",
                '    data() { const x = 1; return { isLoading: false }; },',
                '    provide() { return { swOrderDetailOnLoadingChange: () => {} }; },',
                '    computed: { total() { return 1; } },',
                "    watch: { 'order.id'() {} },",
                '    methods: { onSave() {} },',
                '};',
            ].join('\n'),
            errors: [
                {
                    message:
                        'inject.repositoryFactory props.value emits.change data.isLoading provide.swOrderDetailOnLoadingChange ' +
                        'computed.total watch.order.id methods.onSave',
                },
            ],
        },
        {
            name: 'finds this accesses and destructuring',
            code: "const { isLoading, total: sum } = this; this.isEditing = true; this['config'];",
            errors: [
                { message: 'destructured.isLoading' },
                { message: 'destructured.total' },
                { message: 'this.isEditing' },
                { message: 'this.config' },
            ],
        },
    ],
});

const templateRule = {
    meta: { type: 'problem', schema: [] },
    create(context) {
        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
            VExpressionContainer(node) {
                templateMemberReferences(node).forEach((identifier) => context.report({ node: identifier, message: identifier.name }));
            },
        });
    },
};

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('member-usage (template)', templateRule, {
    valid: [],
    invalid: [
        {
            name: 'reports instance members but not v-for or slot variables',
            code: [
                '<template>',
                '    <div v-for="item in items" :key="item.id">{{ dateFilter(item.createdAt) }}</div>',
                '    <sw-grid><template #column="{ row }">{{ row.name }} {{ isLoading }}</template></sw-grid>',
                '</template>',
            ].join('\n'),
            errors: [{ message: 'items' }, { message: 'dateFilter' }, { message: 'isLoading' }],
        },
    ],
});
