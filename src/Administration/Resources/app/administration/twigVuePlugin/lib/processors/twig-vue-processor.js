/**
 * @package admin
 */

// eslint-plugin-vue 10 ships as ESM under dist/ (the processor sits on the
// module's `default` export); older layouts exported it directly.
const eslintPluginVueProcessorModule = require('eslint-plugin-vue/dist/processor');
const eslintPluginVueProcessor = eslintPluginVueProcessorModule.default ?? eslintPluginVueProcessorModule;

const templateTagBefore = '<template>\n';
const templateTagAfter = '</template>';

// Files that preprocess wrapped in a template tag. ESLint runs the processor again on the wrapped block,
// which is not wrapped twice, so only the pass that wrapped shifts the fixes back.
const wrappedFiles = new Set();

function twigPreParser(code) {
    return `${templateTagBefore}${code}${templateTagAfter}`;
}

module.exports = {
    processors: {
        'twig-vue': {
            // takes text of the file and filename
            preprocess(text, filename) {
                /**
                 * ESLint parses changed text again. To prevent an endless loop we only
                 * process .twig files which weren't parsed before to a Vue SFC.
                 */
                // ESLint hands processors the text with its byte order mark but applies fixes to the text
                // without it, so a kept mark would shift every fix by one character.
                const content = text.replace(/^\uFEFF/, '');
                const isWrapped = !content.startsWith(templateTagBefore);
                const parsedText = isWrapped ? twigPreParser(content) : content;

                if (isWrapped) {
                    wrappedFiles.add(filename);
                } else {
                    wrappedFiles.delete(filename);
                }

                return [
                    { text: parsedText, filename: filename },
                ];
            },

            // takes a Message[][] and filename
            postprocess(messages, filename) {
                const isWrapped = wrappedFiles.has(filename);

                // reduce line - 1 to remove the fake <template> tag
                messages.forEach(messageObjects => {
                    messageObjects.forEach(message => {
                        if (!isWrapped) {
                            return;
                        }

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
                    // No fix, already processed, or a text this processor did not wrap?
                    if (!m.fix || m.twigVue || !isWrapped) {
                        return;
                    }

                    m.fix.range = [
                        m.fix.range[0] - templateTagBeforeLength,
                        m.fix.range[1] - templateTagBeforeLength,
                    ];

                    /**
                     * Altering messages will cause this postprocess to run again.
                     * Therefor add an identifier that this given message got altered already.
                     */
                    m.twigVue = true;
                });

                return finalMessagesFiltered;
            },

            supportsAutofix: true // (optional, defaults to false)
        }
    }
};
