import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    type GuideRule,
    type Meta,
    type Profile,
    type Rules,
    classIsInternal,
    classify,
    collectRange,
    gitShowReader,
    globToRegExp,
    isTestPath,
    loadRulesFromIndex,
    parseDiff,
    parseMeta,
    rangeSides,
    resolveMergeBase,
    workingTreeReader,
} from './classify.ts';

const diffFor = (path: string, hunk: string): string => `diff --git a/${path} b/${path}
index 0000000..1111111 100644
--- a/${path}
+++ b/${path}
@@ -1,1 +1,1 @@
${hunk}
`;

const deletedDiffFor = (path: string, hunk: string): string => `diff --git a/${path} b/${path}
deleted file mode 100644
index 1111111..0000000
--- a/${path}
+++ /dev/null
@@ -1,1 +0,0 @@
${hunk}
`;

const rulesOf = (guides: GuideRule[], existing: string[] = guides.map((rule) => rule.guide)): Rules => ({
    source: 'working-tree',
    guides,
    guideExists: (slug) => existing.includes(slug),
    guideTitle: (slug) => `Title of ${slug}`,
});

const root = mkdtempSync(join(tmpdir(), 'sw-review-classify-'));
const writeSource = (path: string, content: string): void => {
    mkdirSync(dirname(join(root, path)), { recursive: true });
    writeFileSync(join(root, path), content);
};

const run = (files: string[], diff: string, meta?: Meta, rules: Rules = rulesOf([])): Profile =>
    classify({ files, diff, base: 'trunk', meta, root, rules });

const signalsOf = (files: string[], diff: string, meta?: Meta): string[] => run(files, diff, meta).signals;

const PUBLIC_CLASS = `<?php

namespace Shopware\\Core\\Checkout\\Cart;

/**
 * Public API.
 */
class CartService
{
    /**
     * @internal
     */
    public function __construct()
    {
    }
}
`;

const INTERNAL_CLASS = `<?php

namespace Shopware\\Core\\Checkout\\Cart;

/**
 * @internal
 */
final class CartHelper
{
}
`;

writeSource('src/Core/Checkout/Cart/CartService.php', PUBLIC_CLASS);
writeSource('src/Core/Checkout/Cart/CartHelper.php', INTERNAL_CLASS);

test('parseDiff keeps +/- lines and skips headers, hunk headers and context', () => {
    const diff = diffFor('src/Foo.php', ' context\n-old line\n+new line\n\\ No newline at end of file');

    assert.deepEqual(parseDiff(diff), {
        lines: [
            { path: 'src/Foo.php', sign: '-', text: 'old line' },
            { path: 'src/Foo.php', sign: '+', text: 'new line' },
        ],
        deleted: new Set(),
    });
});

test('parseDiff takes the path of a deleted file from the --- line', () => {
    const { lines, deleted } = parseDiff(deletedDiffFor('src/Gone.php', '-<?php'));

    assert.deepEqual(lines, [{ path: 'src/Gone.php', sign: '-', text: '<?php' }]);
    assert.deepEqual(deleted, new Set(['src/Gone.php']));
});

test('parseDiff normalises CRLF', () => {
    const diff = diffFor('docs/a.md', '-old\n+new').replaceAll('\n', '\r\n');

    assert.deepEqual(parseDiff(diff).lines, [
        { path: 'docs/a.md', sign: '-', text: 'old' },
        { path: 'docs/a.md', sign: '+', text: 'new' },
    ]);
});

test('globToRegExp follows bash case semantics', () => {
    assert.ok(globToRegExp('src/*/Command/*.php').test('src/Core/Framework/Command/Foo.php'));
    assert.ok(globToRegExp('*config.xml').test('src/Core/System/Resources/config/config.xml'));
    assert.ok(globToRegExp('a?c').test('abc'));
    assert.ok(!globToRegExp('a?c').test('ac'));
    assert.ok(!globToRegExp('src/*').test('xsrc/a'));
    assert.ok(!globToRegExp('*.php').test('Foo.phpx'));
    assert.ok(!globToRegExp('UPGRADE-*.md').test('UPGRADE-6.7xmd'));
    assert.ok(globToRegExp('a+(b)').test('a+(b)'));
});

test('classIsInternal reads the docblock above the class declaration only', () => {
    assert.equal(classIsInternal(INTERNAL_CLASS), true);
    assert.equal(classIsInternal(PUBLIC_CLASS), false);
    assert.equal(classIsInternal('<?php\n/** @internal */\nreadonly final class Foo {}\n'), true);
    assert.equal(classIsInternal('<?php\n/**\n * @internal\n */\nfunction foo() {}\n'), false);
    assert.equal(classIsInternal('<?php\n/**\n * @internal\n */\n#[Package(\'core\')]\nabstract class Foo {}\n'), true);
});

test('isTestPath', () => {
    for (const path of ['tests/unit/FooTest.php', 'src/Core/tests/a.php', 'src/Core/Test/Foo.php', 'app/test/a.js', 'a.spec.js', 'src/a.test.ts']) {
        assert.ok(isTestPath(path), path);
    }
    for (const path of ['src/Core/Testing/Foo.php', 'src/Core/Checkout/Cart/CartService.php', 'testsuite/a.php']) {
        assert.ok(!isTestPath(path), path);
    }
});

test('path classes take the first matching class per file', () => {
    const classes = (path: string): string[] => run([path], '').path_classes;

    assert.deepEqual(classes('tests/unit/FooTest.php'), ['tests']);
    assert.deepEqual(classes('src/Administration/Resources/app/administration/src/a.ts'), ['admin']);
    assert.deepEqual(classes('src/Storefront/Theme/ThemeService.php'), ['storefront']);
    assert.deepEqual(classes('src/Core/Checkout/Cart/CartService.php'), ['core']);
    assert.deepEqual(classes('.github/workflows/a.yml'), ['config-build']);
    assert.deepEqual(classes('UPGRADE-6.8.md'), ['release-docs']);
    assert.deepEqual(classes('foo.lock'), ['generated']);
    assert.deepEqual(classes('coding-guidelines/core/a.md'), ['docs']);
    assert.deepEqual(classes('bin/console'), ['other']);
    assert.deepEqual(run(['b.md', 'a.yml'], '').path_classes, ['config-build', 'docs']);
});

test('php_src, php_src_without_tests and tests_changed come from the file list', () => {
    assert.deepEqual(signalsOf(['src/Core/Foo.php'], ''), ['php_src', 'php_src_without_tests']);
    assert.deepEqual(signalsOf(['src/Core/Foo.php', 'tests/unit/FooTest.php'], ''), ['php_src', 'tests_changed']);
    assert.deepEqual(signalsOf(['src/Core/Test/Foo.php'], ''), []);
});

const SIGNAL_CASES: Array<[string, string, string]> = [
    ['deprecation', 'src/Core/Foo.js', '+ * @deprecated tag:v6.8.0'],
    ['deprecation', 'src/Core/Foo.js', '-#[BCChange\\ReturnType]'],
    ['release_docs', 'UPGRADE-6.8.md', '+text'],
    ['migration', 'src/Core/Migration/V6_7/Migration1.js', '+x'],
    ['migration', 'src/Core/Foo.js', '+class Foo extends MigrationStep'],
    ['scheduled_task', 'src/Core/ScheduledTask/a.js', '+x'],
    ['scheduled_task', 'src/Core/Foo.js', '+#[AsMessageHandler]'],
    ['command', 'src/Core/Command/FooCommand.php', '+x'],
    ['command', 'src/Core/Foo.js', '+class Foo extends Command'],
    ['request_input', 'src/Core/Foo.js', '+$request->get(\'id\')'],
    ['filesystem', 'src/Core/Foo.js', '+$fs->deleteDirectory($dir);'],
    ['filesystem', 'src/Core/Content/Media/a.js', '+x'],
    ['cache', 'src/Core/Foo.js', '+$response->headers->set(\'Cache-Control\', \'no-cache\');'],
    ['acl_route', 'src/Core/Foo.js', '+#[Route(path: \'/api/foo\')]'],
    ['admin_ui', 'src/Administration/Resources/app/administration/src/app/a.ts', '+x'],
    ['storefront_ui', 'src/Storefront/Resources/views/a.twig', '+x'],
    ['storefront_ui', 'src/Core/Resources/snippet/en.json', '+x'],
    ['config', 'src/Core/System/Resources/config/config.xml', '+x'],
    ['config', 'src/Core/Foo.js', '+$value = getenv(\'FOO\');'],
    ['api_contract', 'src/Core/Checkout/Cart/SalesChannel/CartLoadRoute.php', '+x'],
    ['extension_point', 'src/Core/Checkout/Cart/Event/FooEvent.php', '+x'],
    ['extension_point', 'src/Core/Foo.js', '+class Foo extends Event'],
    ['twig', 'src/Storefront/a.js', '+{% block foo %}'],
];

for (const [signal, path, hunk] of SIGNAL_CASES) {
    test(`signal ${signal} from ${path}: ${hunk}`, () => {
        assert.ok(signalsOf([path], diffFor(path, hunk)).includes(signal));
    });
}

test('anchors ignore docs, release docs and generated files', () => {
    const keyword = '+Use `CacheInvalidator::invalidate()` after a `#[Route(` with `_acl`; the `ScheduledTask` purges later.';
    for (const path of ['coding-guidelines/core/http-cache-and-assets.md', 'UPGRADE-6.8.md', 'src/Storefront/Resources/app/storefront/dist/storefront/js/all.js', '.agents/skills/sw-review/scripts/classify.ts']) {
        const signals = signalsOf([path], diffFor(path, keyword));
        assert.deepEqual(signals.filter((signal) => ['cache', 'acl_route', 'scheduled_task'].includes(signal)), [], path);
    }
    assert.ok(signalsOf(['src/Core/Cache.php'], diffFor('src/Core/Cache.php', keyword)).includes('cache'));
    assert.ok(!signalsOf(['.agents/skills/sw-review/guides/index.json'], diffFor('.agents/skills/sw-review/guides/index.json', '+ "extends MigrationStep"')).includes('migration'));
});

test('anchors ignore context lines and test files', () => {
    assert.ok(!signalsOf(['src/Core/Foo.js'], diffFor('src/Core/Foo.js', ' * @deprecated tag:v6.8.0')).includes('deprecation'));
    assert.ok(!signalsOf(['tests/unit/a.js'], diffFor('tests/unit/a.js', '+ * @deprecated tag:v6.8.0')).includes('deprecation'));
    assert.ok(!signalsOf(['src/Core/Foo.js'], diffFor('src/Core/Foo.js', '+class Foo extends Commander')).includes('command'));
});

test('di fires on a removed service definition and on an added decoration', () => {
    const removedTag = diffFor('src/Core/Framework/DependencyInjection/services.php', '-    ->tag(\'kernel.event_subscriber\');');
    const addedDecoration = diffFor('src/Core/Content/Resources/config/services.xml', '+<service id="Foo" decorates="Bar"/>');

    assert.ok(signalsOf(['src/Core/Framework/DependencyInjection/services.php'], removedTag).includes('di'));
    assert.ok(signalsOf(['src/Core/Content/Resources/config/services.xml'], addedDecoration).includes('di'));
});

test('di does not fire on a new tagged service or outside DI files', () => {
    const newService = diffFor('src/Core/Content/Resources/config/services.xml', '+<service id="Foo">\n+    <tag name="kernel.event_subscriber"/>\n+</service>');
    const elsewhere = diffFor('src/Core/Content/Foo.php', '-    ->tag(\'kernel.event_subscriber\');');

    assert.ok(!signalsOf(['src/Core/Content/Resources/config/services.xml'], newService).includes('di'));
    assert.ok(!signalsOf(['src/Core/Content/Foo.php'], elsewhere).includes('di'));
});

test('public_surface is confirmed for a public class and not for an @internal class', () => {
    const publicPath = 'src/Core/Checkout/Cart/CartService.php';
    const internalPath = 'src/Core/Checkout/Cart/CartHelper.php';

    assert.ok(signalsOf([publicPath], diffFor(publicPath, '+    public function add(): void')).includes('public_surface'));
    assert.ok(!signalsOf([internalPath], diffFor(internalPath, '+    public function add(): void')).includes('public_surface'));
    assert.ok(!signalsOf(['src/Core/Missing.php'], diffFor('src/Core/Missing.php', '+    private function add(): void')).includes('public_surface'));
});

test('public_surface skips @internal candidates and stops at the first public one', () => {
    const internalPath = 'src/Core/Checkout/Cart/CartHelper.php';
    const publicPath = 'src/Core/Checkout/Cart/CartService.php';
    const diff = diffFor(internalPath, '+    public function a(): void') + diffFor(publicPath, '+    protected function b(): void');

    assert.ok(signalsOf([internalPath, publicPath], diff).includes('public_surface'));
});

test('public_surface and removal fire for a deleted class without a checkout lookup', () => {
    const path = 'src/Core/Checkout/Cart/Gone.php';
    const signals = signalsOf([path], deletedDiffFor(path, '-    public function gone(): void'));

    assert.ok(signals.includes('public_surface'));
    assert.ok(signals.includes('removal'));
});

test('removal fires for a deleted non-test src file and a removed public method of a public class', () => {
    const publicPath = 'src/Core/Checkout/Cart/CartService.php';
    const internalPath = 'src/Core/Checkout/Cart/CartHelper.php';

    assert.ok(signalsOf(['src/Core/a.js'], deletedDiffFor('src/Core/a.js', '-x')).includes('removal'));
    assert.ok(!signalsOf(['src/Core/Test/a.js'], deletedDiffFor('src/Core/Test/a.js', '-x')).includes('removal'));
    assert.ok(signalsOf([publicPath], diffFor(publicPath, '-    public function old(): void')).includes('removal'));
    assert.ok(!signalsOf([internalPath], diffFor(internalPath, '-    public function old(): void')).includes('removal'));
});

test('sync_path_change needs scheduled_task together with command or request_input', () => {
    const task = 'src/Core/ScheduledTask/CleanupTask.php';
    const command = 'src/Core/Command/CleanupCommand.php';

    assert.ok(signalsOf([task, command], '').includes('sync_path_change'));
    assert.ok(!signalsOf([task], '').includes('sync_path_change'));
    assert.ok(!signalsOf([command], '').includes('sync_path_change'));
});

test('metadata signals', () => {
    const php = ['src/Core/Foo.php'];

    assert.deepEqual(signalsOf([], '', { fork: true }), ['fork_pr']);
    assert.deepEqual(signalsOf([], '', { labels: ['external-contribution'] }), ['external']);
    assert.deepEqual(signalsOf([], '', { author_association: 'FIRST_TIME_CONTRIBUTOR' }), ['external']);
    assert.deepEqual(signalsOf([], '', { author_association: 'MEMBER', labels: ['bug'] }), []);
    assert.ok(signalsOf(php, '', { fixes_issue: true }).includes('fix_without_test'));
    assert.ok(!signalsOf([...php, 'tests/unit/FooTest.php'], '', { fixes_issue: true }).includes('fix_without_test'));
    assert.deepEqual(run([], '', { fork: true, author_association: 'NONE', labels: ['a'] }).metadata, {
        fork: true,
        author_association: 'NONE',
        labels: ['a'],
    });
    assert.deepEqual(run([], '').metadata, { fork: false, author_association: '', labels: [] });
});

test('path groups bucket files and count changed lines, largest first', () => {
    const files = [
        'src/Core/Checkout/Cart/A.php',
        'src/Core/Checkout/Cart/B.php',
        'src/Administration/Resources/app/administration/src/module/sw-order/page/index.ts',
        'src/Administration/Resources/app/administration/src/app/main.ts',
        'tests/unit/Core/FooTest.php',
        'composer.json',
    ];
    const diff = diffFor(files[0], '+a\n+b')
        + diffFor(files[2], '+a\n-b\n+c')
        + diffFor(files[3], '+a\n-b\n+c')
        + diffFor(files[4], '+a');

    assert.deepEqual(run(files, diff).path_groups, [
        { group: 'src/Administration/Resources/app/administration/src/app', files: 1, changed_lines: 3 },
        { group: 'src/Administration/Resources/app/administration/src/module/sw-order', files: 1, changed_lines: 3 },
        { group: 'src/Core/Checkout', files: 2, changed_lines: 2 },
        { group: 'tests/unit', files: 1, changed_lines: 1 },
        { group: '(root)', files: 1, changed_lines: 0 },
    ]);
});

test('guide selection reports matched_by in rule order and missing guide files', () => {
    const rules = rulesOf([
        { guide: 'unmatched', personas: ['maintainer'], signals_any: ['cache'], paths_any: ['docs/*'] },
        {
            guide: 'matched',
            personas: ['architecture'],
            anchors_any: ['@deprecated'],
            paths_any: ['src/*'],
            always_for: ['php_src'],
            signals_any: ['deprecation', 'php_src'],
        },
    ], ['unmatched']);
    const profile = run(['src/Core/Foo.php'], diffFor('src/Core/Foo.php', '+ * @deprecated tag:v6.8.0'), undefined, rules);

    assert.deepEqual(profile.guides, [{
        guide: 'matched',
        personas: ['architecture'],
        matched_by: ['signal:deprecation', 'signal:php_src', 'always:php_src', 'path:src/*', 'anchor:@deprecated'],
        file_exists: false,
    }]);
    assert.deepEqual(profile.all_guides, [
        { guide: 'unmatched', title: 'Title of unmatched', personas: ['maintainer'] },
        { guide: 'matched', title: 'Title of matched', personas: ['architecture'] },
    ]);
    assert.equal(profile.rules_source, 'working-tree');
});

test('loadRulesFromIndex reads titles from the guide files next to the index', () => {
    const guides = join(root, 'guides');
    mkdirSync(guides, { recursive: true });
    writeFileSync(join(guides, 'index.json'), JSON.stringify({ guides: [{ guide: 'a', personas: [] }] }));
    writeFileSync(join(guides, 'a.md'), '---\nname: a\ntitle:   Is this the platform\'s job?\n---\ntitle: second\n');
    const rules = loadRulesFromIndex(join(guides, 'index.json'));

    assert.equal(rules.source, 'working-tree');
    assert.deepEqual(rules.guides, [{ guide: 'a', personas: [] }]);
    assert.equal(rules.guideTitle('a'), 'Is this the platform\'s job?');
    assert.equal(rules.guideExists('a'), true);
    assert.equal(rules.guideExists('b'), false);
    assert.equal(rules.guideTitle('b'), '');
});

test('size counts non-empty file entries and all changed lines, test files included', () => {
    const diff = diffFor('src/Core/a.js', '+a\n-b') + diffFor('tests/unit/a.js', '+c');

    assert.deepEqual(run(['src/Core/a.js', '', 'tests/unit/a.js'], diff).size, { files: 2, changed_lines: 3, over_cap: false });
});

test('over_cap above 200 files or 5000 changed lines', () => {
    const files = (n: number): string[] => Array.from({ length: n }, (_, i) => `docs/${i}.md`);
    const lines = (n: number): string => diffFor('docs/a.md', Array.from({ length: n }, () => '+x').join('\n'));

    assert.equal(run(files(200), lines(5000)).size.over_cap, false);
    assert.equal(run(files(201), '').size.over_cap, true);
    assert.equal(run(files(1), lines(5001)).size.over_cap, true);
});

test('public_surface uses the supplied reader and treats a missing file as not internal', () => {
    const path = 'src/Core/Checkout/Cart/CartService.php';
    const diff = diffFor(path, '+    public function add(): void');
    const withReader = (readFile: (file: string) => string | null): string[] =>
        classify({ files: [path], diff, base: 'trunk', root, rules: rulesOf([]), readFile }).signals;

    assert.ok(!withReader(() => INTERNAL_CLASS).includes('public_surface'));
    assert.ok(withReader(() => PUBLIC_CLASS).includes('public_surface'));
    assert.ok(withReader(() => null).includes('public_surface'));
});

test('parseMeta reads a file or the JSON itself', () => {
    const file = join(root, 'meta.json');
    writeFileSync(file, '{"fork": true}');

    assert.deepEqual(parseMeta(file), { fork: true });
    assert.deepEqual(parseMeta(' {"labels": ["a"]}'), { labels: ['a'] });
    assert.equal(parseMeta(join(root, 'missing.json')), undefined);
});

test('rangeSides reads <base>...<head> and defaults the head to HEAD', () => {
    assert.deepEqual(rangeSides('abc123...feature'), { base: 'abc123', head: 'feature' });
    assert.deepEqual(rangeSides('abc123...'), { base: 'abc123', head: 'HEAD' });
    assert.deepEqual(rangeSides('abc123'), { base: 'abc123', head: 'HEAD' });
});

// A small repository: the class is public on `base` (checked out) and @internal on `feature`
// (not named `head`: that collides with HEAD on case-insensitive filesystems), and only `base`
// carries a router index. Range mode must read the touched file from `feature` and the rules
// from the merge base, whatever the working tree holds.
const repo = mkdtempSync(join(tmpdir(), 'sw-review-range-'));
const CLASS_PATH = 'src/Core/Checkout/Cart/CartService.php';
const INTERNAL_HEAD = `<?php

namespace Shopware\\Core\\Checkout\\Cart;

/**
 * @internal
 */
class CartService
{
    public function add(): void
    {
    }
}
`;
const git = (...args: string[]): string => execFileSync(
    'git',
    ['-C', repo, '-c', 'user.name=t', '-c', 'user.email=t@example.com', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', ...args],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
).trim();
const put = (path: string, content: string): void => {
    mkdirSync(dirname(join(repo, path)), { recursive: true });
    writeFileSync(join(repo, path), content);
};
git('init', '-q', '-b', 'base');
put(CLASS_PATH, PUBLIC_CLASS);
put('.agents/skills/sw-review/guides/index.json', JSON.stringify({ guides: [{ guide: 'bc-php', personas: ['architecture'], always_for: ['php_src'] }] }));
put('.agents/skills/sw-review/guides/bc-php.md', '---\ntitle: BC\n---\n');
git('add', '-A');
git('commit', '-q', '-m', 'base');
const baseSha = git('rev-parse', 'HEAD');
git('switch', '-q', '-c', 'feature');
put(CLASS_PATH, INTERNAL_HEAD);
git('add', '-A');
git('commit', '-q', '-m', 'head');
git('switch', '-q', 'base');

test('range mode reads touched files from the head of the range, not the working tree', () => {
    assert.equal(resolveMergeBase(repo, 'base...feature'), baseSha);
    assert.equal(resolveMergeBase(repo, 'base...nope'), null);
    assert.equal(classIsInternal(gitShowReader(repo, 'feature')(CLASS_PATH) ?? ''), true);
    assert.equal(gitShowReader(repo, 'feature')('src/Missing.php'), null);
    assert.equal(classIsInternal(workingTreeReader(repo)(CLASS_PATH) ?? ''), false);

    const { files, diff, head } = collectRange(repo, 'base...feature');
    assert.deepEqual(files, [CLASS_PATH]);
    assert.equal(head, 'feature');
    const fromHead = classify({ files, diff, base: 'base', root: repo, rules: rulesOf([]), readFile: gitShowReader(repo, head) });
    const fromTree = classify({ files, diff, base: 'base', root: repo, rules: rulesOf([]) });

    assert.ok(!fromHead.signals.includes('public_surface'), 'head says @internal');
    assert.ok(fromTree.signals.includes('public_surface'), 'working tree is the public base');
});

test('CLI --range takes the rules from the merge base and --meta as inline JSON', () => {
    const stdout = execFileSync(process.execPath, [
        fileURLToPath(new URL('./classify.ts', import.meta.url)),
        '--range', 'base...feature',
        '--base', 'base',
        '--root', repo,
        '--meta', '{"fork": true}',
    ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    const profile = JSON.parse(stdout) as Profile;

    assert.equal(profile.rules_source, baseSha);
    assert.equal(profile.metadata.fork, true);
    assert.ok(!profile.signals.includes('public_surface'));
    assert.deepEqual(profile.guides.map((guide) => guide.guide), ['bc-php']);
    assert.deepEqual(profile.all_guides, [{ guide: 'bc-php', title: 'BC', personas: ['architecture'] }]);
});
