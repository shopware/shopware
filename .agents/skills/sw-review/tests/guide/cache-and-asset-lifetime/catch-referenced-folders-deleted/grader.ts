// Real diff: a change deletes unused theme/<hash> folders from theme:compile and
// theme:change, with the 24-hour grace period measured from the files' mtime.
// Outcome: cached pages still referenced the deleted folders, so their CSS/JS
// returned 404.
// Guide loaded: cache-and-asset-lifetime only.
// Expectation: a CACHE- finding on the command or the deleter, major or blocking.

import { all, file, findings, grade, majorOrBlocking, rule } from './_lib.ts';

const FILES = [
    'src/Storefront/Theme/Command/ThemeCompileCommand.php',
    'src/Storefront/Theme/Command/ThemeChangeCommand.php',
    'src/Storefront/Theme/UnusedThemeDirectoryDeleter.php',
];

grade((g, out) => {
    g.checkSchemaPersona();

    g.check('persona-attribution', out.persona === 'architecture');
    g.require('cache-rule-finding', findings(out).some(rule('CACHE-')));
    g.require('cache-on-cleanup-file', findings(out).some(all(rule('CACHE-'), file(...FILES))));
    g.check('cache-major-or-block', findings(out).some(all(rule('CACHE-'), majorOrBlocking)));
});
