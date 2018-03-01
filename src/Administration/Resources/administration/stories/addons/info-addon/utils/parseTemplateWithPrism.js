import Prism from 'prismjs';
import 'prismjs/plugins/normalize-whitespace/prism-normalize-whitespace';
import 'prismjs/components/prism-less';

Prism.plugins.NormalizeWhitespace.setDefaults({
    'remove-trailing': true,
    'remove-indent': true,
    'left-trim': true,
    'right-trim': true
});

const normalizeWhitespacePlugin = Prism.plugins.NormalizeWhitespace;

function stripHTMLFromTemplate(template) {
    const doc = new DOMParser().parseFromString(template, 'text/html');
    return doc.body.textContent || '';
}

function parseTemplateWithPrism(template, language = 'html') {
    if (language !== 'html') {
        template = stripHTMLFromTemplate(template);
    }
    template = normalizeWhitespacePlugin.normalize(template);
    return Prism.highlight(template, Prism.languages[language]);
}

export default parseTemplateWithPrism;
