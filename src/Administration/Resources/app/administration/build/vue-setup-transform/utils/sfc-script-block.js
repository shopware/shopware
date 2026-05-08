/**
 * @sw-package framework
 */

const { AttributeParser } = require('./attribute-parser');
const { ShopwareSetupTransformError } = require('./transform-error');

/**
 * @typedef {import('@vue/compiler-sfc').SFCScriptBlock} SFCScriptBlock
 * @typedef {import('./attributes').Attributes} Attributes
 *
 * @typedef {object} ScriptBlock
 * @property {'script' | 'scriptSetup'} type
 * @property {number} start
 * @property {number} end
 * @property {number} contentStart
 * @property {string} content
 * @property {Attributes} attributes
 * @property {string} passthroughAttributesSource
 */

/**
 * Verifies that a possible `<script` token is the tag Vue parsed, not text inside an attribute.
 *
 * @param {string} source
 * @param {number} scriptStart
 * @param {number} contentStart
 * @returns {boolean}
 */
function endsAtVueContentStart(source, scriptStart, contentStart) {
    let quote = null;
    const expectedTagEnd = contentStart - 1;

    for (let index = scriptStart + '<script'.length; index <= expectedTagEnd; index += 1) {
        const character = source[index];

        if (quote) {
            if (character === quote) {
                quote = null;
            }

            continue;
        }

        if (character === '"' || character === "'") {
            quote = character;
            continue;
        }

        if (character === '>') {
            return index === expectedTagEnd;
        }
    }

    return false;
}

/**
 * Finds the full opening tag start because Vue only exposes the script content offset.
 *
 * @param {string} source
 * @param {number} contentStart Vue's authoritative offset for the script content.
 * @returns {number}
 */
function findScriptStart(source, contentStart) {
    for (
        let index = source.lastIndexOf('<script', contentStart);
        index !== -1;
        index = source.lastIndexOf('<script', index - 1)
    ) {
        if (/^<script\b/i.test(source.slice(index)) && endsAtVueContentStart(source, index, contentStart)) {
            return index;
        }
    }

    throw new ShopwareSetupTransformError('Unable to locate Vue SFC <script> start tag.', contentStart);
}

/**
 * Builds the shared block shape consumed by semantic normalization and lowering.
 *
 * @param {string} source
 * @param {SFCScriptBlock} descriptorBlock
 * @param {'script' | 'scriptSetup'} type
 * @returns {ScriptBlock}
 */
function toScriptBlock(source, descriptorBlock, type) {
    const contentStart = descriptorBlock.loc.start.offset;
    const start = findScriptStart(source, contentStart);
    const end = source.indexOf('>', descriptorBlock.loc.end.offset) + 1;
    const attributes = AttributeParser.parse(
        source.slice(start + '<script'.length, contentStart - 1),
        start + '<script'.length,
    );

    return {
        type,
        start,
        end,
        contentStart,
        content: descriptorBlock.content,
        attributes,
        passthroughAttributesSource: attributes.toSourceWithout([
            'sw-component',
            'sw-override',
        ]),
    };
}

module.exports = {
    toScriptBlock,
};
