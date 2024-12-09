import jsdoc2md from 'jsdoc-to-markdown';
import { promises as fs } from 'node:fs';
import path from 'path';

/* input and output paths */
const inputFile = 'src/helper/*.js';

/* get template data */
const templateData = await jsdoc2md.getTemplateData({ files: inputFile });

/* reduce templateData to an array of class names */
const classNames = templateData.filter(i => i.kind === 'module').map(i => i.name);

/* create a documentation file for each class */
for (const className of classNames) {
    const template = `
    {{#module name="${className}"}}
        {{>docs}}
    {{/module}}
    `;
    console.log(`rendering ${className}, template: ${template}`);
    const output = await jsdoc2md.render({ data: templateData, template: template });
    await fs.writeFile(path.resolve(`docs-md/${className}.md`), output);
}