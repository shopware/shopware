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

/** Each `[line, message]` pair: a line of the code, which the rule marks with `message` above it. */
const invalidScript = (name, ...pairs) => ({
    name,
    code: pairs.map(([line]) => line).join('\n'),
    output: pairs.flatMap(([line, message]) => [scriptTodo(message), line]).join('\n'),
    errors: pairs.map(([, message]) => ({ message })),
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-flag-gated-behaviour', rule, {
    valid: [
        { name: 'ignores reads of the sorting', code: "Component.override('sw-media-library', { methods: { a() { return this.sorting.sortBy; } } });" },
        {
            name: 'ignores a sorting the override sets itself, which the new default does not affect',
            code: [
                "Component.override('sw-media-library', { data() { return { sorting: { sortBy: 'fileName' } }; } });",
                "Component.override('sw-media-display-options', { props: { sorting: { type: Object, default: () => ({}) } } });",
            ].join('\n'),
        },
        { name: 'ignores encodeURI of other values', code: 'const url = encodeURI(this.media.url);\nconst path = encodeURI(thumbnailPath);' },
        { name: 'ignores sendMailTemplate with templateData', code: 'this.mailService.sendMailTemplate(mail, name, template, media, salesChannelId, false, [], { order });' },
        { name: 'ignores sendMailTemplate with spread arguments', code: 'this.mailService.sendMailTemplate(...args);' },
        { name: 'ignores sendMailTemplate of other services', code: 'this.acmeMailer.sendMailTemplate(mail, name, template);' },
    ],
    invalid: [
        invalidScript('marks overrides of the media library criteria', [
            "Component.override('sw-media-library', { computed: { nextMediaCriteria() { return this.$super('nextMediaCriteria'); } } });",
            declaration('sw-media-library', 'nextMediaCriteria'),
        ]),
        invalidScript('marks overrides of buildSourceSet', [
            "Component.override('sw-media-preview-v2', { methods: { buildSourceSet(media) { return ''; } } });",
            declaration('sw-media-preview-v2', 'buildSourceSet'),
        ]),
        invalidScript('marks overrides of onMailTemplateChange', [
            "Component.override('sw-order-send-document-modal', { methods: { onMailTemplateChange(id, template) {} } });",
            declaration('sw-order-send-document-modal', 'onMailTemplateChange'),
        ]),
        invalidScript(
            'marks encodeURI of thumbnail URLs, also nested',
            ['const url = encodeURI(thumbnail.url);', ENCODE],
            ['const first = window.encodeURI(media.thumbnails[0].url);', ENCODE],
        ),
        invalidScript(
            'marks sendMailTemplate with missing, null or undefined templateData',
            ['await this.mailService.sendMailTemplate(recipient, name, mailTemplate, media, salesChannelId);', SEND],
            ["Shopware.Service('mailService').sendMailTemplate(mail, name, template, media, salesChannelId, false, [], null, typeId);", SEND],
            ['this.mailService.sendMailTemplate(mail, name, template, media, salesChannelId, false, [], undefined);', SEND],
        ),
    ],
});
