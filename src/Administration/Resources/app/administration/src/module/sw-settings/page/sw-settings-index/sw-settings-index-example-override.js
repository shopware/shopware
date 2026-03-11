/**
 * Example override using legacy Twig block syntax to test the
 * Twig → Native Block Runtime Adapter (PR #15347).
 *
 * This simulates a plugin that was written before sw-settings-index
 * migrated from {% block %} to <sw-block>. The shim in sw-block/index.ts
 * should detect the legacy override at boot time and bridge it into the
 * native block system — keeping the override visible without any changes
 * to this file.
 *
 * Expected console output when the settings page mounts:
 * [Shopware Deprecation] Block "sw_settings_content_card_view_header" in component
 * "sw-settings-index" uses a legacy Twig override. Migrate to:
 * <sw-block extends="sw_settings_content_card_view_header">...</sw-block>
 */
Shopware.Component.override('sw-settings-index', {
    template: `
{% block sw_settings_content_card_view_header %}
{% parent %}
<div style="background:#fff3e0;padding:8px 12px;margin-top:8px;border-radius:4px;border:1px solid #ff9800;font-size:13px;margin-bottom:32px;">
    <strong>&#9888;&#65039; Legacy Twig shim — Vue feature tests (PR #15347)</strong>

    <div style="margin-top:6px;">
        <strong>Data access:</strong>
        {{ Object.keys(settingsGroups).length }} group(s) loaded
        &mdash; search query: "{{ searchQuery || '(empty)' }}"
    </div>

    <div v-if="searchQuery" style="color:#e65100;margin-top:4px;">
        &#x1F50D; <strong>v-if active:</strong> filtering for "{{ searchQuery }}"
    </div>
    <div v-else style="color:#2e7d32;margin-top:4px;">
        &#x2705; <strong>v-else active:</strong> no search query entered yet
    </div>
</div>
{% endblock %}
`,
});
