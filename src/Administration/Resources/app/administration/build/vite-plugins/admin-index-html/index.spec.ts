/**
 * @sw-package framework
 */
import fs from 'fs';
import path from 'path';
import AdminIndexHtmlPlugin from './index';

type Handler = (html: string) => string;

function render(html: string, data: Record<string, unknown>): string {
    const hook = AdminIndexHtmlPlugin(data).transformIndexHtml as { handler: Handler };

    return hook.handler(html);
}

describe('build/vite-plugins/admin-index-html', () => {
    it('registers a pre transformIndexHtml hook for the dev server', () => {
        const plugin = AdminIndexHtmlPlugin({});

        expect(plugin.name).toBe('shopware-vite-plugin-admin-index-html');
        expect(plugin.apply).toBe('serve');
        expect(plugin.transformIndexHtml).toHaveProperty('order', 'pre');
        expect(plugin.transformIndexHtml).toHaveProperty('handler', expect.any(Function));
    });

    it('renders a flat key', () => {
        expect(render('<p><%- serviceRegistryUrl %></p>', { serviceRegistryUrl: 'https://registry.example' })).toBe(
            '<p>https://registry.example</p>',
        );
    });

    it('renders a nested key', () => {
        expect(
            render('<%-pageLoadingScreen.markup%>', { pageLoadingScreen: { markup: '<div class="loader"></div>' } }),
        ).toBe('<div class="loader"></div>');
    });

    it('renders a Buffer as its content', () => {
        expect(render('<style><%- style %></style>', { style: Buffer.from('body { color: red; }') })).toBe(
            '<style>body { color: red; }</style>',
        );
    });

    it.each([
        ['undefined', '<%- value %>', { value: undefined }],
        ['null', '<%- value %>', { value: null }],
        ['an unknown key', '<%- unknown %>', {}],
        ['an unknown nested key', '<%- unknown.nested %>', {}],
    ])('renders %s as an empty string', (_, html, data) => {
        expect(render(`[${html}]`, data)).toBe('[]');
    });

    it('renders false as "false"', () => {
        expect(render('hideUpdateModule: <%- hideUpdateModule %>,', { hideUpdateModule: false })).toBe(
            'hideUpdateModule: false,',
        );
    });

    it('does not interpret replacement patterns in values', () => {
        expect(render('<%- value %>', { value: "$& $' $1" })).toBe("$& $' $1");
    });

    it('keeps text without placeholders unchanged', () => {
        const html = '<html><body><div id="app"></div></body></html>';

        expect(render(html, { value: 'unused' })).toBe(html);
    });

    it('renders the index.html of the Administration', () => {
        const html = fs.readFileSync(path.resolve(__dirname, '../../../index.html'), 'utf8');

        const result = render(html, {
            featureFlags: JSON.stringify({ ADMIN_VITE_8: true }),
            serviceRegistryUrl: 'https://registry.example',
            analyticsGatewayUrl: undefined,
            hideUpdateModule: true,
            pageLoadingScreen: {
                script: Buffer.from('/* loading script */'),
                style: Buffer.from('/* loading style */'),
                markup: Buffer.from('<div id="loading-screen"></div>'),
            },
        });

        expect(result).not.toContain('<%');
        expect(result).toContain('window._features_ = {"ADMIN_VITE_8":true};');
        expect(result).toContain('/* loading script */');
        expect(result).toContain('/* loading style */');
        expect(result).toContain('<div id="loading-screen"></div>');
        expect(result).toContain("analyticsGatewayUrl: '',");
        expect(result).toContain('hideUpdateModule: true,');
        expect(result).toContain("serviceRegistryUrl: 'https://registry.example',");
        expect(result).toContain('<script type="module" src="/src/index.ts"></script>');
    });
});
