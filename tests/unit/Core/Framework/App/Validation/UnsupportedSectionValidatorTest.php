<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\Severity;
use Shopware\Core\Framework\App\Validation\Error\UnsupportedSectionError;
use Shopware\Core\Framework\App\Validation\UnsupportedSectionValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UnsupportedSectionValidator::class)]
class UnsupportedSectionValidatorTest extends TestCase
{
    public function testManifestWithOnlySupportedSectionsProducesNoError(): void
    {
        $errors = (new UnsupportedSectionValidator())->validate(AppFixture::createManifest(), null);

        static::assertSame([], $errors);
    }

    public function testEachUnsupportedSectionProducesANotice(): void
    {
        $manifest = Manifest::createFromXml(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                <meta>
                    <name>testApp</name>
                    <label>testApp</label>
                    <author>shopware AG</author>
                    <copyright>(c) by shopware AG</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                <future-section/>
                <another-future-section/>
            </manifest>
            XML);

        $errors = (new UnsupportedSectionValidator())->validate($manifest, null);

        static::assertCount(2, $errors);
        static::assertInstanceOf(UnsupportedSectionError::class, $errors[0]);
        static::assertSame(['section' => 'future-section'], $errors[0]->getParameters());
        static::assertSame(Severity::Notice, $errors[0]->getSeverity());
        static::assertInstanceOf(UnsupportedSectionError::class, $errors[1]);
        static::assertSame(['section' => 'another-future-section'], $errors[1]->getParameters());
    }
}
