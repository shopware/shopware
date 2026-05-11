---
title: Mail templates store only merchant overrides on top of shipped defaults
issue: NEXT-00000
author: Soner Sayakci
author_email: s.sayakci@gmail.com
author_github: shyim
---
# Core
* Added `Shopware\Core\Content\MailTemplate\Defaults\MailTemplateDefaultsRegistry` (public service) as the source of truth for shipped mail template defaults, loaded from `Resources/mail-templates/` directories (core, plugins, apps).
* Added `Shopware\Core\Content\MailTemplate\Defaults\MailTemplateResolver` which merges merchant overrides on top of registered defaults and returns a `ResolvedMailTemplate` with a per-field `_source` provenance map.
* Added `MailTemplateDefault` and `ResolvedMailTemplate` DTOs.
* Added `Shopware\Core\Content\MailTemplate\MailTemplateMaterializer` (public service) that lazily creates the parent `mail_template_type` and `mail_template` rows for a registry-only template when something concretely needs a UUID (merchant override, flow assignment, sales-channel binding). Idempotent under concurrency.
* Migrated shipped content for all 44 system mail templates into `src/Core/Content/MailTemplate/Resources/mail-templates/` (manifest XML + per-locale `html.twig` / `plain.twig` files).
* Added `Migration1778514014ClearSystemMailTemplateTranslations` that on `isInstallation()` deletes seeded `mail_template_translation` rows for `system_default` templates so the registry is the sole source of truth on fresh installs. Upgrades leave existing rows untouched.
* Removed `Shopware\Core\Content\MailTemplate\MailTemplateSetPersister`. Plugin and app installs no longer write to `mail_template` or `mail_template_type`; they only register their declared templates with the registry. Lazy materialization handles rows when needed.
* Changed `MailTemplateTranslationDefinition` to drop the `Required` flag from `subject`, `content_html`, and `content_plain`; the columns were already nullable at the SQL level.
* Changed `MailTemplateService::loadTemplate`/`preview` and `GetDataAndSendRequestResolver` to route through `MailTemplateResolver` before rendering or sending.
* Changed `PluginLifecycleService` and `Shopware\Core\Framework\App\Lifecycle\Persister\MailTemplatePersister` to register/remove their declared templates against the registry on install/update/uninstall — no DB writes.
* Deprecated `Shopware\Core\Migration\Traits\UpdateMailTrait` (and its `MailUpdate` / `MailSubjectUpdate` structs); to change a shipped default, edit the twig files under `Resources/mail-templates/` instead.
# API
* Added `GET /api/_action/mail-template/{id}/resolved?languageId=...` returning the merchant overrides merged with the shipped default plus a `_source` map.
* Added `GET /api/_action/mail-template/{id}/defaults?languageId=...` returning only the shipped default.
* Added `POST /api/_action/mail-template/{id}/reset` (`{fields, languageId}`) nulling the named override fields and removing the translation row if it becomes empty.
# Administration
* Added `fetchResolvedMailTemplate`, `fetchMailTemplateDefaults`, and `resetMailTemplate` to `mailService` wrapping the three new admin routes.
* Changed `sw-mail-template-detail` to load the resolved view alongside the entity, show the shipped default as the input placeholder when a field has no override, and surface a "Reset to default" link for fields whose `_source` is `user`.
