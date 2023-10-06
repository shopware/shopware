---
title: Recompile all dependent themes on change
issue: NEXT-19364
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
author_github: @ssltg
---
# Storefront
* Added `Migration1641476963ThemeDependentIds` for adding new mapping table `theme_child`.
* Added `ThemeChildDefinition`
* Added new ManyToManyAssociationField `dependentThemes` to `ThemeDefinition`.
* Deprecated `childThemes` in `ThemeDefinition` and `ThemeEntity`. Will be removed in `v6.5.0`.
* Added new property `dependentThemes` to `ThemeEntity`
* Changed `ThemeLifecycleHandler` to recompile all dependent themes on theme change.
* Changed `ThemeLifecycleService::refreshTheme` to add dependend theme mapping to themes.
* Added `ThemeSalesChannel` struct to represent theme to theme mapping
* Added `ThemeSalesChannelCollection`
* Added `ThemeService::compileThemeById` to compile all saleschannel dependent themes of a given `themeId`
* Added `ThemeIndexerEvent`
* Added `ThemeIndexingMessage`
* Added `ThemeIndexer` to add `parentThemeId` to `dependentThemes`
