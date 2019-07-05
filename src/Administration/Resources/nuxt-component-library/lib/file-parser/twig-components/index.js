const fs = require('fs');
const Twig = require('twig');
const pretty = require('pretty');

Twig.extend((TwigCore) => {
    /**
     * Remove tokens 2, 3, 4 and 8.
     * This tokens are used for functions and data output.
     * Since the data binding is done in Vue this could lead to syntax issues.
     * We are only using the block system for template inheritance.
     *
     * @type {Array<any>}
     */
    TwigCore.token.definitions = [
        TwigCore.token.definitions[0],
        TwigCore.token.definitions[1],
        TwigCore.token.definitions[5],
        TwigCore.token.definitions[6],
        TwigCore.token.definitions[7],
        TwigCore.token.definitions[9],
        TwigCore.token.definitions[10]
    ];

    /**
     * Twig inheritance extension.
     * The parent function is used as a statement tag.
     * This is used to prevent syntax issues between Twig and Vue.
     * Use `{% parent %}` to print out the parent content of a block.
     */
    TwigCore.exports.extendTag({
        type: 'parent',
        regex: /^parent/,
        next: [],
        open: true,

        parse(token, context, chain) {
            return {
                chain,
                output: TwigCore.placeholders.parent
            };
        }
    });

    /** Make the placeholders available in the exposed Twig object. */
    TwigCore.exports.placeholders = TwigCore.placeholders;

    /** Make the Twig template cache registry available. */
    TwigCore.exports.getRegistry = function getRegistry() {
        return TwigCore.Templates.registry;
    };

    /** Provide possibility to clear the template cache registry */
    TwigCore.exports.clearRegistry = function clearRegistry() {
        TwigCore.Templates.registry = {};
    };
});

function extractImportFile(importList) {
    if (!importList || !importList.length) {
        return null;
    }

    const definedLessImport = importList.reduce((accumulator, item) => {
        if (item.indexOf('.html.twig') !== -1) {
            accumulator = item;
        }
        return accumulator;
    }, null);

    return definedLessImport;
}

function getFullFilePath(basePath, importFile) {
    return importFile.replace('.', basePath);
}

function getFileContent(fileName) {
    return fs.readFileSync(fileName, {
        encoding: 'utf-8'
    });
}

function extractSlots(content) {
    const slots = content.match(/<slot[^>]*>/gs);
    if (!slots || slots.length <= 0) {
        return [];
    }

    return slots.map((slot) => {
        let name = 'default';
        const slotContent = slot.match(/<slot(.*)>/s)[1];
        const attrs = slotContent.match(/\s?:?([\w|_|-]+)="([\w|_|-]+|\{.*\})"/g);

        if (!attrs || attrs.length <= 0) {
            return {
                isDefault: true,
                name: 'default',
                isScopedSlot: false,
                variables: []
            }
        }

        const slotVariables = [];
        attrs.forEach((keyVal) => {
            const [, attr, val] = keyVal.match(/\s?:?(.*)="(.*)"/);
            if (attr === 'name') {
                name = val;
                return;
            }

            if (attr === 'v-bind') {
                const bindings = val.substr(1, val.length - 2).split(/,\s?/);
                slotVariables.push(...bindings)
                return
            }

            slotVariables.push(attr);
        });

        return {
            isDefault: name === "default",
            name,
            isScopedSlot: slotVariables.length > 0,
            variables: slotVariables
        };
    });
}

function extractBlocks(file, content) {    
    const template = Twig.twig({
        id: file.source.name,
        data: content
    });

    const templateBlocks = template.render({}, {
        output: 'blocks'
    });

    return Object.keys(templateBlocks).map((key) => {
        const template = templateBlocks[key];

        return {
            name: key,
            template: pretty(template, { ocd: true })
        };
    });
}

function parseFile(file, importsList) {
    const fileName = extractImportFile(importsList)

    if (!fileName) {
        return {
            slots: [],
            blocks: []
        };
    }
    const content = getFileContent(getFullFilePath(file.directory, fileName));

    const slots = extractSlots(content).filter((obj, pos, arr) => {
        return arr.map(mapObj => mapObj.name).indexOf(obj.name) === pos;
    }) || [];
    const blocks = extractBlocks(file, content);

    return {
        slots,
        blocks
    };
}

module.exports = (file, imports) => {
    return parseFile(file, imports);
}