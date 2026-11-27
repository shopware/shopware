/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-mail-template-api');
const data = require('./no-removed-mail-template-api.data');

const TEST_MAIL = data.calls[0].message;
const BUILD = data.calls[1].message;
const VALIDATE = data.literals[1].message;
const member = (name) => data.members.find((entry) => entry.name === name).message;
const SEARCH_TERM = member('searchTerm');
const todo = (text) => `// TODO Codemod(6.8, mail-template-api): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-removed-mail-template-api (script)', rule, {
    valid: [
        {
            name: 'ignores the remaining mail service methods',
            code: 'this.mailService.sendMailTemplate(a, b, c);\nthis.mailService.previewMailTemplate(id);\nthis.mailService.simulateMailTemplate(parts, event);',
        },
        { name: 'ignores the preview route', code: "const url = '/_action/mail-template/preview';" },
        {
            name: 'ignores the members outside sw-mail-template-detail',
            code: "Component.override('sw-flow-mail-send-modal', { computed: { hasTemplateData() { return this.replaceContent(''); } } });",
        },
        {
            name: 'ignores searchTerm outside the mail template lists',
            code: "Component.override('sw-product-list', { props: { searchTerm: String }, watch: { searchTerm() {} } });",
        },
        {
            name: 'ignores other kinds of searchTerm in the lists',
            code: "Component.override('sw-mail-template-list', { data() { return { searchTerm: '' }; } });",
        },
    ],
    invalid: [
        {
            name: 'marks the removed service methods',
            code: [
                'await this.mailService.testMailTemplate(mail, template, media, salesChannelId, typeId, templateId);',
                "Shopware.Service('mailService').buildRenderPreview(type, template);",
            ].join('\n'),
            output: [
                todo(TEST_MAIL),
                'await this.mailService.testMailTemplate(mail, template, media, salesChannelId, typeId, templateId);',
                todo(BUILD),
                "Shopware.Service('mailService').buildRenderPreview(type, template);",
            ].join('\n'),
            errors: [{ message: TEST_MAIL }, { message: BUILD }],
        },
        {
            name: 'marks the removed routes',
            code: "httpClient.post('/_action/mail-template/build', payload);\nhttpClient.post('/api/_action/mail-template/validate', payload);",
            output: [
                todo(BUILD),
                "httpClient.post('/_action/mail-template/build', payload);",
                todo(VALIDATE),
                "httpClient.post('/api/_action/mail-template/validate', payload);",
            ].join('\n'),
            errors: [{ message: BUILD }, { message: VALIDATE }],
        },
        {
            name: 'marks the removed members in overrides of sw-mail-template-detail',
            code: [
                "Component.override('sw-mail-template-detail', {",
                '    computed: {',
                '        hasTemplateData() {',
                '            return false;',
                '        },',
                '    },',
                '    methods: {',
                '        mailPreviewContent() {',
                "            return this.replaceContent('');",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-mail-template-detail', {",
                '    computed: {',
                `        ${todo(member('hasTemplateData'))}`,
                '        hasTemplateData() {',
                '            return false;',
                '        },',
                '    },',
                '    methods: {',
                `        ${todo(member('mailPreviewContent'))}`,
                '        mailPreviewContent() {',
                `            ${todo(member('replaceContent'))}`,
                "            return this.replaceContent('');",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: member('hasTemplateData') }, { message: member('mailPreviewContent') }, { message: member('replaceContent') }],
        },
        {
            name: 'marks the searchTerm prop and watcher in overrides of the lists',
            code: [
                "Component.override('sw-mail-header-footer-list', {",
                '    props: { searchTerm: String },',
                '    watch: { searchTerm() { this.getList(); } },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-mail-header-footer-list', {",
                `    ${todo(SEARCH_TERM)}`,
                '    props: { searchTerm: String },',
                `    ${todo(SEARCH_TERM)}`,
                '    watch: { searchTerm() { this.getList(); } },',
                '});',
            ].join('\n'),
            errors: [{ message: SEARCH_TERM }, { message: SEARCH_TERM }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-removed-mail-template-api (template)', rule, {
    valid: [
        {
            name: 'ignores the members in templates of other components',
            filename: '/plugin/src/acme-mail/acme-mail.html.twig',
            code: '<template>\n    <div v-if="hasTemplateData" />\n</template>',
        },
        {
            name: 'ignores search-term on other components',
            filename: '/plugin/src/acme-list/acme-list.html.twig',
            code: '<template>\n    <sw-product-list :search-term="term" />\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks the members in templates that override sw-mail-template-detail',
            filename: '/plugin/src/sw-mail-template-detail/sw-mail-template-detail.html.twig',
            code: '<template>\n    <div v-if="hasTemplateData" />\n</template>',
            output: [
                '<template>',
                `    {# TODO Codemod(6.8, mail-template-api): ${member('hasTemplateData')} #}`,
                '    <div v-if="hasTemplateData" />',
                '</template>',
            ].join('\n'),
            errors: [{ message: member('hasTemplateData') }],
        },
        {
            name: 'marks search-term on the list tags',
            filename: '/plugin/src/sw-mail-template-index/sw-mail-template-index.html.twig',
            code: '<template>\n    <sw-mail-template-list :search-term="term" />\n    <sw-mail-header-footer-list search-term="footer" />\n</template>',
            output: [
                '<template>',
                `    {# TODO Codemod(6.8, mail-template-api): ${data.searchTermAttributeMessage} #}`,
                '    <sw-mail-template-list :search-term="term" />',
                `    {# TODO Codemod(6.8, mail-template-api): ${data.searchTermAttributeMessage} #}`,
                '    <sw-mail-header-footer-list search-term="footer" />',
                '</template>',
            ].join('\n'),
            errors: [{ message: data.searchTermAttributeMessage }, { message: data.searchTermAttributeMessage }],
        },
    ],
});
