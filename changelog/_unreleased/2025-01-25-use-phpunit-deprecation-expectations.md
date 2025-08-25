---
title: Use PHPUnit deprecation expectations in tests
issue: 12085
author: Martin Bens
author_email: m.bens@shopware.com
author_github: @SpiGAndromeda
---
# Core
* Changed `\Shopware\Tests\Unit\Core\Framework\FeatureTest::testCallSilentIfInactiveProvider()` to use `expectUserDeprecationMessageMatches()` instead of manual error handlers
* Changed `\Shopware\Tests\Unit\Core\Framework\FeatureTest::callSilentIfInactiveProvider()` data provider to return boolean flags instead of assertion closures
* Changed `\Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser\FeatureFlagCallTokenParserTest::testCodeRun()` to use `expectUserDeprecationMessageMatches()` instead of manual error handlers