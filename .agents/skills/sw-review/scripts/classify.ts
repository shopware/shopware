/**
 * Deterministic change classification for sw-review.
 *
 * Reads the changed-file list and the unified diff of a pull request and emits one
 * JSON "change profile": path classes, impact signals, size, and the review guides
 * selected from guides/index.json. No LLM is involved, so the routing is
 * reproducible and covered by tests/routing/.
 *
 *   node classify.ts --range <merge-base>...HEAD [--base trunk] [--meta meta.json] \
 *                    [--root <checkout root>] [--rules-ref <merge-base>]
 *   node classify.ts --files files.txt --diff diff.patch [...]        # pre-computed inputs
 *
 * --range: the script runs `git diff` itself (changed paths incl. old names of renames,
 *          and the unified diff). Preferred in CI: the orchestrator then never has to
 *          pass the diff through the model.
 * --rules-ref: read guides/index.json and the guide files from that git ref instead of
 *          the working tree, so a pull request cannot change the rules it is reviewed
 *          against. Defaults to the merge base of --range. The orchestrator takes this
 *          script from the same commit (`git show <merge-base>:<path>`), so the PR cannot
 *          change the code that classifies it either.
 * files.txt: one repo-relative path per line (post-change paths; deleted files too).
 * --meta:  optional {"fork": bool, "author_association": "...", "labels": [...], "fixes_issue": bool},
 *          as a file or as the JSON itself.
 * Signals are computed on added/removed lines only (never on context lines) and
 * never inside tests/**, docs, release docs or generated files (a guideline that mentions
 * `HttpCache` is not a cache change); the one exception is "public_surface", which reads the touched
 * file to confirm that its class is not @internal: from the head of --range, so the
 * lookup sees the reviewed head whatever is checked out, or from the working tree for
 * pre-computed inputs.
 *
 * Globs follow bash `case` semantics (`*` crosses `/`), regexes are the POSIX ERE
 * strings of the original grep-based script with `[[:space:]]` read as `\s`.
 */

import { execFileSync } from 'node:child_process';
import { readFileSync, realpathSync, statSync } from 'node:fs';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

export interface Meta { fork?: boolean; author_association?: string; labels?: string[]; fixes_issue?: boolean }
export interface GuideRule { guide: string; personas: string[]; signals_any?: string[]; paths_any?: string[]; anchors_any?: string[]; always_for?: string[] }
export interface Rules { source: string; guides: GuideRule[]; guideExists(slug: string): boolean; guideTitle(slug: string): string }
export interface MergedCommit { sha: string; subject: string }
/** Source of a repo-relative file (post-change side); null when it does not exist there. */
export type SourceReader = (path: string) => string | null;
export interface ClassifyInput { files: string[]; diff: string; base: string; meta?: Meta; root: string; rules: Rules; mergedFromBase?: MergedCommit[]; readFile?: SourceReader }
export interface Profile {
    base_ref: string;
    rules_source: string;
    path_classes: string[];
    signals: string[];
    metadata: { fork: boolean; author_association: string; labels: string[] };
    size: { files: number; changed_lines: number; over_cap: boolean };
    path_groups: Array<{ group: string; files: number; changed_lines: number }>;
    merged_from_base: MergedCommit[];
    guides: Array<{ guide: string; personas: string[]; matched_by: string[]; file_exists: boolean }>;
    all_guides: Array<{ guide: string; title: string; personas: string[] }>;
    ci?: unknown;
}

export type DiffLine = { path: string; sign: '+' | '-'; text: string };

const SKILL_REL = '.agents/skills/sw-review';
const USAGE = 'usage: classify.ts (--range <base>...<head> | --files files.txt --diff diff.patch) [--base trunk] [--meta meta.json] [--root dir] [--rules-ref <ref>] [--index guides/index.json]';

/** Bash `case` glob: `*` matches any string including `/`, `?` one character. */
export function globToRegExp(glob: string): RegExp {
    let source = '';
    for (const char of glob) {
        if (char === '*') {
            source += '.*';
        } else if (char === '?') {
            source += '.';
        } else {
            source += char.replace(/[\\^$.+()[\]{}|/]/, '\\$&');
        }
    }

    return new RegExp(`^${source}$`, 'su');
}

/** POSIX ERE as used with `grep -E`; only `[[:space:]]` needs translating. */
export function ereToRegExp(pattern: string): RegExp {
    return new RegExp(pattern.replaceAll('[[:space:]]', '\\s'));
}

const globCache = new Map<string, RegExp>();
function globMatch(path: string, glob: string): boolean {
    let re = globCache.get(glob);
    if (re === undefined) {
        re = globToRegExp(glob);
        globCache.set(glob, re);
    }

    return re.test(path);
}

const anyGlob = (path: string, globs: string[]): boolean => globs.some((glob) => globMatch(path, glob));

const TEST_GLOBS = ['tests/*', '*/tests/*', '*/Test/*', '*/test/*', '*.spec.*', '*.test.*'];
export const isTestPath = (path: string): boolean => anyGlob(path, TEST_GLOBS);
export const isPhpSrc = (path: string): boolean => globMatch(path, 'src/*.php') && !isTestPath(path);

/** awk's default field splitting: `$n` of a header line, '' when absent. */
function field(line: string, n: number): string {
    return line.split(/[ \t\n]+/)[n - 1] ?? '';
}

/**
 * Changed lines per file. Only the +/- lines of hunks; the +++/--- headers and context
 * lines are skipped. A deleted file (+++ /dev/null) takes its path from the --- line.
 * Deleted files have no +++ b/ path; take them from "deleted file mode" blocks.
 */
export function parseDiff(diff: string): { lines: DiffLine[]; deleted: Set<string> } {
    const lines: DiffLine[] = [];
    const deleted = new Set<string>();
    let file = '';
    let old = '';
    let block = '';
    for (const line of diff.replaceAll('\r', '').split('\n')) {
        if (line.startsWith('diff --git ')) {
            file = '';
            old = '';
            block = field(line, 3).replace(/^a\//, '');
            continue;
        }
        if (line.startsWith('deleted file mode')) {
            deleted.add(block);
        }
        if (line.startsWith('--- ')) {
            old = field(line, 2).replace(/^a\//, '');
            continue;
        }
        if (line.startsWith('+++ ')) {
            file = field(line, 2).replace(/^b\//, '');
            if (file === '/dev/null') {
                file = old;
            }
            continue;
        }
        if (line.startsWith('@@ ') || file === '') {
            continue;
        }
        if (line.startsWith('+') || line.startsWith('-')) {
            lines.push({ path: file, sign: line[0] as '+' | '-', text: line.slice(1) });
        }
    }

    return { lines, deleted };
}

/**
 * True when the docblock directly above the first class, interface, trait or enum
 * declaration carries @internal. An @internal on a constructor or a method (which
 * InternalMethodRule requires for DI service constructors) does not make the class
 * internal. The docblock rules run before the declaration rule on the same line.
 */
export function classIsInternal(source: string): boolean {
    let inDoc = false;
    let doc = '';
    let lastDoc = '';
    for (const line of source.split('\n')) {
        if (/^\s*\/\*\*/.test(line)) {
            inDoc = true;
            doc = '';
        }
        if (inDoc) {
            doc += `\n${line}`;
        }
        if (line.includes('*/') && inDoc) {
            inDoc = false;
            lastDoc = doc;
            doc = '';
        }
        if (/^\s*(abstract\s+|final\s+|readonly\s+)*(class|interface|trait|enum)\s/.test(line)) {
            return lastDoc.includes('@internal');
        }
    }

    return false;
}

function isFile(path: string): boolean {
    try {
        return statSync(path, { throwIfNoEntry: false })?.isFile() ?? false;
    } catch {
        return false;
    }
}

function git(root: string, args: string[], quiet = false): string {
    return execFileSync('git', ['-C', root, ...args], {
        encoding: 'utf8',
        maxBuffer: 1024 * 1024 * 1024,
        stdio: ['ignore', 'pipe', quiet ? 'ignore' : 'inherit'],
    });
}

function gitSucceeds(root: string, args: string[]): boolean {
    try {
        git(root, args, true);
        return true;
    } catch {
        return false;
    }
}

/** Files of the working tree under root. */
export function workingTreeReader(root: string): SourceReader {
    return (path) => (isFile(`${root}/${path}`) ? readFileSync(`${root}/${path}`, 'utf8') : null);
}

/**
 * Files as committed at a git ref (`git show <ref>:<path>`), so the lookup sees the reviewed
 * head even when another branch, or the base, is checked out.
 */
export function gitShowReader(root: string, ref: string): SourceReader {
    return (path) => {
        try {
            return git(root, ['show', `${ref}:${path}`], true);
        } catch {
            return null;
        }
    };
}

/** The two sides of `<base>...<head>`; a bare `<base>` means `<base>...HEAD`. */
export function rangeSides(range: string): { base: string; head: string } {
    const [base, head] = range.split('...');

    return { base, head: head === undefined || head === '' ? 'HEAD' : head };
}

/** Merge base of the two sides of a range; null when git cannot resolve it. */
export function resolveMergeBase(root: string, range: string): string | null {
    const { base, head } = rangeSides(range);
    try {
        return git(root, ['merge-base', base, head], true).trim();
    } catch {
        return null;
    }
}

/** First `title:` line of a guide file, without the key and the following whitespace. */
function titleOf(content: string): string {
    const line = content.split('\n').find((candidate) => candidate.startsWith('title:'));

    return line === undefined ? '' : line.slice('title:'.length).replace(/^\s*/, '');
}

/** Rule files from a git ref; null when the ref has no router index. */
export function loadRulesFromRef(root: string, ref: string): Rules | null {
    const spec = (file: string): string => `${ref}:${SKILL_REL}/guides/${file}`;
    if (!gitSucceeds(root, ['cat-file', '-e', spec('index.json')])) {
        return null;
    }
    const index = JSON.parse(git(root, ['show', spec('index.json')])) as { guides?: GuideRule[] };

    return {
        source: ref,
        guides: index.guides ?? [],
        guideExists: (slug) => gitSucceeds(root, ['cat-file', '-e', spec(`${slug}.md`)]),
        guideTitle: (slug) => {
            try {
                return titleOf(git(root, ['show', spec(`${slug}.md`)], true));
            } catch {
                return '';
            }
        },
    };
}

/** Rule files from the working tree; the guides live next to the index. */
export function loadRulesFromIndex(indexPath: string): Rules {
    const guidesDir = dirname(indexPath);
    const index = JSON.parse(readFileSync(indexPath, 'utf8')) as { guides?: GuideRule[] };

    return {
        source: 'working-tree',
        guides: index.guides ?? [],
        guideExists: (slug) => isFile(`${guidesDir}/${slug}.md`),
        guideTitle: (slug) => (isFile(`${guidesDir}/${slug}.md`) ? titleOf(readFileSync(`${guidesDir}/${slug}.md`, 'utf8')) : ''),
    };
}

/** Diff, changed paths, merge commits and the head side of a range, read from git. */
export function collectRange(root: string, range: string): { files: string[]; diff: string; mergedFromBase: MergedCommit[]; head: string } {
    const diff = git(root, ['diff', '--no-color', '--no-ext-diff', '-M', range]);
    // changed paths: post-change names plus the old names of renames, so globs match both sides
    const names = git(root, ['diff', '--name-only', '-M', range]).split('\n');
    const renamed = git(root, ['diff', '--name-status', '-M', range])
        .split('\n')
        .map((line) => line.split('\t'))
        .filter((columns) => columns[0].startsWith('R'))
        .map((columns) => columns[1] ?? '');
    const files = [...new Set([...names, ...renamed].filter((path) => path !== ''))].sort();
    const mergedFromBase = git(root, ['log', '--merges', '--format=%h%x09%s', range], true)
        .split('\n')
        .filter((line) => line !== '')
        .map((line) => {
            const [sha, subject] = line.split('\t');
            return { sha, subject };
        });

    return { files, diff, mergedFromBase, head: rangeSides(range).head };
}

const GENERATED_GLOBS = ['*.lock', '*.lock.yml', '*/dist/*', '*.min.js', '*.snap'];
const PATH_CLASSES: Array<[string, string[]]> = [
    ['tests', ['tests/*', '*/tests/*']],
    ['admin', ['src/Administration/*']],
    ['storefront', ['src/Storefront/*']],
    ['core', ['src/*']],
    ['config-build', ['.github/*', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', '*.yaml', '*.yml']],
    ['release-docs', ['UPGRADE-*.md', 'RELEASE_INFO-*.md', 'changelog/*']],
    ['generated', GENERATED_GLOBS],
    ['docs', ['*.md', 'docs/*', 'coding-guidelines/*', 'adr/*']],
];

/** First matching path class, 'other' when none matches. */
export function pathClassOf(path: string): string {
    return PATH_CLASSES.find(([, globs]) => anyGlob(path, globs))?.[0] ?? 'other';
}

/**
 * Lines whose content carries no code signal: tests, docs, release docs, generated files.
 * Generated globs are checked directly because `src/Storefront/*` classes a `dist/` file first.
 */
export const isAnchorFree = (path: string): boolean =>
    isTestPath(path) || anyGlob(path, GENERATED_GLOBS) || ['docs', 'release-docs'].includes(pathClassOf(path));

const cut = (path: string, segments: number): string => path.split('/').slice(0, segments).join('/');

/**
 * Path groups (for splitting a persona's slice deterministically).
 * Key: Core/Storefront/Elasticsearch -> first three segments (src/Core/Checkout); Admin
 * modules -> the module folder; tests -> tests/<suite>; everything else -> first two segments.
 */
export function groupOf(path: string): string {
    if (globMatch(path, 'src/Administration/Resources/app/administration/src/module/*')) {
        return cut(path, 8);
    }
    if (globMatch(path, 'src/Administration/Resources/app/administration/src/*')) {
        return cut(path, 7);
    }
    if (anyGlob(path, ['src/Administration/*', 'src/Core/*', 'src/Storefront/*', 'src/Elasticsearch/*'])) {
        return cut(path, 3);
    }
    if (globMatch(path, 'tests/*') || globMatch(path, '*/*')) {
        return cut(path, 2);
    }

    return '(root)';
}

const countBy = (keys: string[]): Map<string, number> => {
    const counts = new Map<string, number>();
    for (const key of keys) {
        counts.set(key, (counts.get(key) ?? 0) + 1);
    }

    return counts;
};

const compareStrings = (a: string, b: string): number => (a < b ? -1 : a > b ? 1 : 0);

export function classify(input: ClassifyInput): Profile {
    // normalise CRLF so paths and anchors match
    const files = input.files.map((path) => path.replaceAll('\r', '')).filter((path) => path !== '');
    const { lines, deleted } = parseDiff(input.diff);
    // Changed lines of code files only, for anchor matching: a guideline or an UPGRADE entry
    // that names `CacheInvalidator` must not route the cache guide.
    const srcLines = lines.filter((line) => !isAnchorFree(line.path));
    const meta = input.meta ?? {};
    const readSource = input.readFile ?? workingTreeReader(input.root);

    const hit = (pattern: string, sign?: '+' | '-'): boolean => {
        const re = ereToRegExp(pattern);
        return srcLines.some((line) => (sign === undefined || line.sign === sign) && re.test(line.text));
    };
    const hitIn = (glob: string, pattern: string, sign: '+' | '-'): boolean => {
        const re = ereToRegExp(pattern);
        return srcLines.some((line) => line.sign === sign && globMatch(line.path, glob) && re.test(line.text));
    };
    const pathHit = (glob: string): boolean => files.some((path) => globMatch(path, glob));

    // --- path classes ----------------------------------------------------------------
    const classes = new Set<string>();
    let phpSrc = false;
    let phpSrcTests = false;
    for (const path of files) {
        const pathClass = pathClassOf(path);
        classes.add(pathClass);
        if (pathClass === 'tests') {
            phpSrcTests = true;
        }
        if (isPhpSrc(path)) {
            phpSrc = true;
        }
    }

    // --- signals ---------------------------------------------------------------------
    const sig = new Set<string>();
    if (phpSrc) {
        sig.add('php_src');
    }
    if (phpSrc && !phpSrcTests) {
        sig.add('php_src_without_tests');
    }
    if (phpSrcTests) {
        sig.add('tests_changed');
    }

    // public_surface: a candidate line in a non-test PHP file, confirmed only when the
    // file is not @internal (checkout lookup; a deleted file counts as candidate).
    const candidate = '^[[:space:]]*(public|protected) function |__construct\\(';
    if (hit(candidate)) {
        const re = ereToRegExp(candidate);
        const internal = new Map<string, boolean>();
        for (const line of srcLines) {
            if (!globMatch(line.path, 'src/*.php') || !re.test(line.text)) {
                continue;
            }
            if (deleted.has(line.path)) {
                sig.add('public_surface');
                sig.add('removal');
                break;
            }
            if (!internal.has(line.path)) {
                const source = readSource(line.path);
                internal.set(line.path, source !== null && classIsInternal(source));
            }
            if (internal.get(line.path)) {
                continue;
            }
            sig.add('public_surface');
            break;
        }
    }

    if (hit('@deprecated|triggerDeprecationOrThrow\\(|BCChange\\\\|<deprecated|silentUntil')) {
        sig.add('deprecation');
    }
    if (pathHit('UPGRADE-*.md') || pathHit('RELEASE_INFO-*.md')) {
        sig.add('release_docs');
    }
    if (pathHit('src/Core/Migration/*') || pathHit('*/Migration/Migration*.php') || hit('updateDestructive\\(|extends MigrationStep')) {
        sig.add('migration');
    }
    if (pathHit('*/ScheduledTask/*') || hit('ScheduledTask|AsMessageHandler|MessageHandler|DelayStamp')) {
        sig.add('scheduled_task');
    }
    if (pathHit('src/*/Command/*.php') || hit('#\\[AsCommand\\(|extends Command\\b')) {
        sig.add('command');
    }
    if (hit('Request \\$request|->request->|->query->|\\$request->get\\(')) {
        sig.add('request_input');
    }
    if (sig.has('scheduled_task') && (sig.has('command') || sig.has('request_input'))) {
        sig.add('sync_path_change');
    }
    if (hit('deleteDirectory\\(|FilesystemOperator|->deleteDir\\(|unlink\\(|rmdir\\(') || pathHit('src/Storefront/Theme/*') || pathHit('src/Core/Content/Media/*')) {
        sig.add('filesystem');
    }
    if (hit('HttpCache|CacheInvalidator|cache\\.tag|Cache-Control|ETag|addCacheTag|CacheTagCollector|CacheStore')) {
        sig.add('cache');
    }
    if (hit('#\\[Route\\(|#\\[Acl\\(|_acl')) {
        sig.add('acl_route');
    }
    if (pathHit('src/Administration/Resources/app/administration/src/*')) {
        sig.add('admin_ui');
    }
    if (pathHit('src/Storefront/Resources/views/*') || pathHit('src/Storefront/Resources/app/storefront/src/*') || pathHit('src/Storefront/Resources/app/storefront/*.scss') || pathHit('src/*/Resources/snippet/*')) {
        sig.add('storefront_ui');
    }
    if (pathHit('*config.xml') || pathHit('*/Resources/config/packages/*') || pathHit('*shopware.yaml') || pathHit('*feature.yaml') || hit('%env\\(|getenv\\(|ini_get\\(')) {
        sig.add('config');
    }
    // di: a service *contract* change, not any edit of a DI file (those happen in most PHP PRs):
    // alias, decoration, visibility, deprecation or tag changes, or a removed service definition.
    // A removed definition, alias, tag or visibility line is a contract change; a new service with a tag is not.
    for (const diGlob of ['src/*/DependencyInjection/*', 'src/*/Resources/config/*.xml']) {
        if (hitIn(diGlob, '->set\\(|->alias\\(|->tag\\(|->public\\(|->private\\(|->decorate\\(|decorates=|<service |<tag |alias=', '-')
            || hitIn(diGlob, '->decorate\\(|decorates=|->alias\\(|->deprecate\\(|<deprecated|#\\[AsDecorator', '+')) {
            sig.add('di');
        }
    }
    if (pathHit('src/*/SalesChannel/*Route.php') || pathHit('src/Core/*/Api/*') || pathHit('src/Core/Framework/Api/ApiDefinition/Generator/Schema/*')) {
        sig.add('api_contract');
    }
    if (pathHit('src/*Event.php') || pathHit('src/*/Abstract*.php') || hit('extends Event\\b|AbstractExtension|getDecorated\\(')) {
        sig.add('extension_point');
    }
    if ([...deleted].some((path) => globMatch(path, 'src/*') && !isTestPath(path))) {
        sig.add('removal');
    }
    if (hit('^[[:space:]]*(public|protected) function ', '-') && sig.has('public_surface')) {
        sig.add('removal');
    }
    if (hit('\\{% block |sw_include|sw_extends')) {
        sig.add('twig');
    }

    // metadata
    const fork = meta.fork === true;
    const assoc = typeof meta.author_association === 'string' ? meta.author_association : '';
    const labels = Array.isArray(meta.labels) ? meta.labels : [];
    if (fork) {
        sig.add('fork_pr');
    }
    if (labels.includes('external-contribution')) {
        sig.add('external');
    }
    if (['CONTRIBUTOR', 'FIRST_TIME_CONTRIBUTOR', 'FIRST_TIMER', 'NONE'].includes(assoc)) {
        sig.add('external');
    }
    // fix_without_test: a "fix" PR title or fixes-link is known only from metadata; callers may set it.
    if (meta.fixes_issue === true && !phpSrcTests && phpSrc) {
        sig.add('fix_without_test');
    }

    // --- path groups (for splitting a persona's slice deterministically) ------------------
    const groupLines = countBy(lines.map((line) => groupOf(line.path)));
    const pathGroups = [...countBy(files.map(groupOf))]
        .map(([group, count]) => ({ group, files: count, changed_lines: groupLines.get(group) ?? 0 }))
        .sort((a, b) => b.changed_lines - a.changed_lines || compareStrings(a.group, b.group));

    // --- size --------------------------------------------------------------------------
    const size = { files: files.length, changed_lines: lines.length, over_cap: files.length > 200 || lines.length > 5000 };

    // --- guide selection ---------------------------------------------------------------
    const guides: Profile['guides'] = [];
    const allGuides: Profile['all_guides'] = [];
    for (const rule of input.rules.guides) {
        // the overview every worker gets, so a routing miss is not final
        allGuides.push({ guide: rule.guide, title: input.rules.guideTitle(rule.guide), personas: rule.personas });
        const matched = [
            ...(rule.signals_any ?? []).filter((s) => s !== '' && sig.has(s)).map((s) => `signal:${s}`),
            ...(rule.always_for ?? []).filter((s) => s !== '' && sig.has(s)).map((s) => `always:${s}`),
            ...(rule.paths_any ?? []).filter((glob) => glob !== '' && pathHit(glob)).map((glob) => `path:${glob}`),
            ...(rule.anchors_any ?? []).filter((re) => re !== '' && hit(re)).map((re) => `anchor:${re}`),
        ];
        if (matched.length === 0) {
            continue;
        }
        guides.push({ guide: rule.guide, personas: rule.personas, matched_by: matched, file_exists: input.rules.guideExists(rule.guide) });
    }

    return {
        base_ref: input.base,
        rules_source: input.rules.source,
        path_classes: [...classes].sort(compareStrings),
        signals: [...sig].sort(compareStrings),
        metadata: { fork, author_association: assoc, labels },
        size,
        path_groups: pathGroups,
        merged_from_base: input.mergedFromBase ?? [],
        guides,
        all_guides: allGuides,
    };
}

/** `--meta`: a JSON file, or the JSON itself when the value starts with `{`; undefined when neither exists. */
export function parseMeta(value: string): Meta | undefined {
    if (value.trimStart().startsWith('{')) {
        return JSON.parse(value) as Meta;
    }

    return isFile(value) ? (JSON.parse(readFileSync(value, 'utf8')) as Meta) : undefined;
}

const FLAGS: Record<string, 'files' | 'diff' | 'range' | 'base' | 'meta' | 'root' | 'index' | 'rulesRef'> = {
    '--files': 'files',
    '--diff': 'diff',
    '--range': 'range',
    '--base': 'base',
    '--meta': 'meta',
    '--root': 'root',
    '--index': 'index',
    '--rules-ref': 'rulesRef',
};

export function main(argv: string[]): number {
    const opts = { files: '', diff: '', range: '', base: 'trunk', meta: '', root: '.', index: '', rulesRef: '' };
    for (let i = 0; i < argv.length; i += 2) {
        const key = FLAGS[argv[i]];
        if (key === undefined) {
            process.stderr.write(`unknown argument: ${argv[i]}\n`);
            return 2;
        }
        if (i + 1 >= argv.length) {
            process.stderr.write(`missing value for ${argv[i]}\n`);
            return 2;
        }
        opts[key] = argv[i + 1];
    }

    let files: string[];
    let diff: string;
    let mergedFromBase: MergedCommit[] = [];
    let readFile: SourceReader | undefined;
    if (opts.range !== '') {
        if (opts.files !== '' || opts.diff !== '') {
            process.stderr.write('--range cannot be combined with --files/--diff\n');
            return 2;
        }
        let head: string;
        try {
            ({ files, diff, mergedFromBase, head } = collectRange(opts.root, opts.range));
        } catch {
            process.stderr.write(`git diff ${opts.range} failed in ${opts.root}\n`);
            return 2;
        }
        // Touched files come from the head of the range, the rules from its merge base: the
        // result does not depend on what is checked out, and the PR head decides neither.
        readFile = gitShowReader(opts.root, head);
        if (opts.rulesRef === '') {
            opts.rulesRef = resolveMergeBase(opts.root, opts.range) ?? '';
        }
    } else {
        if (opts.files === '' || opts.diff === '') {
            process.stderr.write(`${USAGE}\n`);
            return 2;
        }
        if (!isFile(opts.files)) {
            process.stderr.write(`files list not found: ${opts.files}\n`);
            return 2;
        }
        if (!isFile(opts.diff)) {
            process.stderr.write(`diff not found: ${opts.diff}\n`);
            return 2;
        }
        files = readFileSync(opts.files, 'utf8').split('\n');
        diff = readFileSync(opts.diff, 'utf8');
    }

    // rule files: from the working tree, or from --rules-ref via git show
    let rules = opts.rulesRef === '' ? null : loadRulesFromRef(opts.root, opts.rulesRef);
    if (rules === null) {
        // The base branch has no router index yet (the guides are being introduced by this very
        // PR, or a stacked branch): fall back to the working tree and say so in the output.
        if (opts.rulesRef !== '') {
            process.stderr.write(`note: ${opts.rulesRef} has no ${SKILL_REL}/guides/index.json; using the working tree\n`);
        }
        // The index of the checkout under --root, or the one next to this script when the script
        // runs inside the skill folder (routing tests); a copy in .sw-review/ has no neighbours.
        const candidates = opts.index !== ''
            ? [opts.index]
            : [`${opts.root}/${SKILL_REL}/guides/index.json`, `${dirname(fileURLToPath(import.meta.url))}/../guides/index.json`];
        const index = candidates.find(isFile);
        if (index === undefined) {
            process.stderr.write(`guide index not found: ${candidates.join(', ')}\n`);
            return 2;
        }
        rules = loadRulesFromIndex(index);
    }

    const meta = opts.meta === '' ? undefined : parseMeta(opts.meta);
    const profile = classify({ files, diff, base: opts.base, meta, root: opts.root, rules, mergedFromBase, readFile });
    process.stdout.write(`${JSON.stringify(profile, null, 2)}\n`);

    return 0;
}

function isEntryPoint(): boolean {
    try {
        return process.argv[1] !== undefined && realpathSync(process.argv[1]) === fileURLToPath(import.meta.url);
    } catch {
        return false;
    }
}

if (isEntryPoint()) {
    process.exitCode = main(process.argv.slice(2));
}
