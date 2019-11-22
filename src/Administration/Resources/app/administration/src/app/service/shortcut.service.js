/**
 * @module app/service/shortcut
 */

const { Application } = Shopware;

/**
 *
 * @memberOf module:core/service/shortcut
 * @constructor
 * @method createShortcutService
 * @param {Object} shortcutFactory
 * @param {Number} [keystrokeDelay=1000]
 * @returns {Object}
 */
export default function createShortcutService(shortcutFactory, keystrokeDelay = 1000) {
    let state = {
        buffer: [],
        lastKeyTime: Date.now()
    };

    return {
        startEventListener,
        stopEventListener
    };

    function startEventListener() {
        document.addEventListener('keyup', handleKeyUp);
    }

    function stopEventListener() {
        document.removeEventListener('keyup', handleKeyUp);
    }

    function handleKeyUp(event) {
        if (isRestrictedSource(event)) {
            return false;
        }

        const key = event.key.toUpperCase();
        const currentTime = Date.now();
        const router = Application.getApplicationRoot().$router;

        let buffer = [];

        if (currentTime - state.lastKeyTime > keystrokeDelay) {
            buffer = [key];
        } else {
            buffer = [...state.buffer, key];
        }

        state = {
            buffer: buffer,
            lastKeyTime: currentTime
        };

        const combination = buffer.join('');
        const path = shortcutFactory.getPathByCombination(combination);

        if (!path) {
            return false;
        }

        router.push({ path });

        return true;
    }

    function isRestrictedSource(event) {
        const restrictedTags = /INPUT|TEXTAREA|SELECT/;
        const source = event.srcElement;
        const tagName = source.tagName;

        // editable DIVs are restricted
        if (tagName === 'DIV') {
            return source.isContentEditable;
        }

        return restrictedTags.test(tagName);
    }
}

