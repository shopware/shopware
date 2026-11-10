/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-flag-gated-behaviour');
const data = require('./no-flag-gated-behaviour.data');

const declaration = (component, name) => data.declarations.find((entry) => entry.component === component && entry.name === name).message;
const ENCODE = data.encodeThumbnailUrl.message;
const SEND = data.sendMailTemplate.message;
const scriptTodo = (text) => `// TODO Codemod(6.8, flag-gated-behaviour): ${text}`;

const invalidScript = (name, code, messages) => ({
    name,
    code,
    output: [...messages.map(scriptTodo), code].join('\n'),
    errors: messages.map((message) => ({ message })),
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-flag-gated-behaviour', rule, {
    valid: [
        { name: 'ignores reads of the sorting', code: "Component.override('sw-media-library', { methods: { a() { return this.sorting.sortBy; } } });" },
        { name: 'ignores sorting of other components', code: "Component.override('sw-product-list', { data() { return { sorting: {} }; } });" },
        { name: 'ignores encodeURI of other values', code: 'const url = encodeURI(this.media.url);\nconst path = encodeURI(thumbnailPath);' },
        { name: 'ignores sendMailTemplate with templateData', code: 'this.mailService.sendMailTemplate(mail, name, template, media, salesChannelId, false, [], { order });' },
        { name: 'ignores sendMailTemplate with spread arguments', code: 'this.mailService.sendMailTemplate(...args);' },
        { name: 'ignores sendMailTemplate of other services', code: 'this.acmeMailer.sendMailTemplate(mail, name, template);' },
    ],
    invalid: [
        {
            name: 'marks the sorting declarations of the media overrides',
            code: [
                "Component.override('sw-media-library', {",
                '    data() {',
                "        return { sorting: { sortBy: 'fileName', sortDirection: 'asc' } };",
                '    },',
                '    computed: {',
                '        nextMediaCriteria() {',
                "            return this.$super('nextMediaCriteria');",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-media-library', {",
                '    data() {',
                `        ${scriptTodo(declaration('sw-media-library', 'sorting'))}`,
                "        return { sorting: { sortBy: 'fileName', sortDirection: 'asc' } };",
                '    },',
                '    computed: {',
                `        ${scriptTodo(declaration('sw-media-library', 'nextMediaCriteria'))}`,
                '        nextMediaCriteria() {',
                "            return this.$super('nextMediaCriteria');",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: declaration('sw-media-library', 'sorting') }, { message: declaration('sw-media-library', 'nextMediaCriteria') }],
        },
        invalidScript(
            'marks overrides of buildSourceSet',
            "Component.override('sw-media-preview-v2', { methods: { buildSourceSet(media) { return ''; } } });",
            [declaration('sw-media-preview-v2', 'buildSourceSet')],
        ),
        invalidScript(
            'marks overrides of onMailTemplateChange',
            "Component.override('sw-order-send-document-modal', { methods: { onMailTemplateChange(id, template) {} } });",
            [declaration('sw-order-send-document-modal', 'onMailTemplateChange')],
        ),
        invalidScript('marks encodeURI of thumbnail URLs', 'const url = encodeURI(thumbnail.url);', [ENCODE]),
        invalidScript('marks encodeURI of nested thumbnail URLs', 'const url = window.encodeURI(media.thumbnails[0].url);', [ENCODE]),
        invalidScript(
            'marks sendMailTemplate without templateData',
            'await this.mailService.sendMailTemplate(recipient, name, mailTemplate, media, salesChannelId);',
            [SEND],
        ),
        invalidScript(
            'marks sendMailTemplate with null templateData',
            "Shopware.Service('mailService').sendMailTemplate(mail, name, template, media, salesChannelId, false, [], null, typeId);",
            [SEND],
        ),
    ],
});
