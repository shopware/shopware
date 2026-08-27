---
title: Install translations offline
issue: #19284
author: Martin Krzykawski
author_email: m.krzykawski@shopware.com
author_github: @MartinKrzykawski
---
# Core
* Added an `--offline` option to `translation:install`. It creates the languages and snippet sets for translation files that are already on the filesystem, without contacting the translation repository at all.
  * Useful where the files are provisioned separately: an installation with restricted egress, or a deployment that fetches them once when its artifact is built and then only needs each installation to point at them. `translation:download` is the counterpart that fetches the files without touching the database.
  * Presence of the files is verified per locale, so an incomplete provisioning step fails the command instead of leaving a language with no translations behind it.
  * The metadata store is neither read nor written in this mode, so no request is made and a later regular `translation:install` or `translation:update` still behaves as before.
* Fixed `translation:install` skipping the creation of languages and snippet sets when the translation files were already up to date. Whether a translation is current and whether it is actually installed are separate questions, and the command now answers both: files are re-fetched only when the repository has something newer or when they are missing locally, while the language and snippet set are ensured for every requested locale.
  * Previously a locale whose files were current but whose language had been removed could not be reinstalled — the command reported success without doing anything.
* Added `Shopware\Core\System\Snippet\Service\AbstractTranslationLoader::link()` and `::hasTranslationFiles()` backing the above.
