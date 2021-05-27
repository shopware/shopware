const eslintPluginVueProcessor = require('eslint-plugin-vue/lib/processor');

const templateTagBefore = '<template>\n';
const templateTagAfter = '</template>';

function twigPreParser(code) {
    const addedTemplate = `${templateTagBefore}${code}${templateTagAfter}`;

    /**
     * convert twig block syntax with html comments to avoid parsing problems
     * in the abstract syntax tree (AST) they would be VText elements when `{% block %}`
     * and ignored when `<!--blck -->`
     * the slight abbreviation is on purpose, so the character count of eslint stays same.
     * Replacing with html elements, so nesting and block names are available to the linter
     * failed, because it might lead to invalid vue templates for example slot-templates
     */
    const newCode = addedTemplate
        .replace('{% parent() %}', '<!--prent()-->')
        .replace(/({# @?)(.*)( #})/gm, (match, p1, p2) => {
            // parse twig comments @see https://regex101.com/r/vyx15C/1
            return `<!--${p1.length === 3 ? p2.substr(1) : p2}-->`;
        })
        .replace(/{% block/g, '<!--blck')
        .replace(/{% endblock/g, '<!--endblck')
        .replace(/ %}/g, '-->');

    return newCode;
}

module.exports = {
    processors: {
        'twig-vue': {
            // takes text of the file and filename
            preprocess(text, filename) {
                const parsedText = twigPreParser(text);

                return [
                    { text: parsedText, filename: filename }
                ];
            },

            // takes a Message[][] and filename
            postprocess(messages, filename) {
                // reduce line - 1 to remove the fake <template> tag
                messages.forEach(messageObjects => {
                    messageObjects.forEach(message => {
                        message.line -= 1;
                        message.endLine -= 1;
                    });
                });

                // process through vue procesor and return a one-dimensional array of the messages you want to keep
                const finalMessages = eslintPluginVueProcessor.postprocess(messages);

                // filter errors for fake <bloc> elements
                const finalMessagesFiltered = finalMessages
                    .filter(message => !(message.ruleId === 'vue/html-self-closing' && message.message.includes('<bloc>')));

                const templateTagBeforeLength = templateTagBefore.length;

                finalMessagesFiltered.forEach(m => {
                    if (!m.fix) {
                        return;
                    }

                    m.fix.range = [
                        m.fix.range[0] - templateTagBeforeLength,
                        m.fix.range[1] - templateTagBeforeLength,
                    ];
                });

                return finalMessagesFiltered;
            },

            supportsAutofix: true // (optional, defaults to false)
        }
    }
};
