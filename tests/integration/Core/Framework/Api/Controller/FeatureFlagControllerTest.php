<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class FeatureFlagControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testEnable(): void
    {
        Feature::fake(['FEATURE_ONE'], function (): void {
            Feature::registerFeatures([
                'FEATURE_ONE' => [
                    'name' => 'Feature 1',
                    'default' => true,
                    'toggleable' => true,
                    'active' => false,
                    'description' => 'This is a test feature',
                ],
            ]);

            $url = '/api/_action/feature-flag/enable/FEATURE_ONE';
            $client = $this->getBrowser();
            $client->request('POST', $url);

            static::assertSame('', $client->getResponse()->getContent());
            static::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
            static::assertTrue(Feature::isActive('FEATURE_ONE'));
        });
    }

    public function testEnableMajorFeature(): void
    {
        Feature::fake(['FEATURE_ONE'], function (): void {
            Feature::registerFeatures([
                'FEATURE_ONE' => [
                    'name' => 'Feature 1',
                    'default' => true,
                    'toggleable' => true,
                    'active' => false,
                    'major' => true,
                    'description' => 'This is a test feature',
                ],
            ]);

            $isActive = Feature::isActive('FEATURE_ONE');
            $url = '/api/_action/feature-flag/enable/FEATURE_ONE';
            $client = $this->getBrowser();
            $client->request('POST', $url);

            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            static::assertNotEmpty($response['errors'][0]);
            unset($response['errors'][0]['trace']);
            static::assertSame([
                'errors' => [
                    [
                        'status' => '400',
                        'code' => FeatureException::MAJOR_FEATURE_CANNOT_BE_TOGGLE,
                        'title' => 'Bad Request',
                        'detail' => 'Feature "FEATURE_ONE" is major so it cannot be toggled.',
                        'meta' => [
                            'parameters' => [
                                'feature' => 'FEATURE_ONE',
                            ],
                        ],
                    ],
                ],
            ], $response);
            static::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
            static::assertEquals($isActive, Feature::isActive('FEATURE_ONE'));
        });
    }

    public function testEnableNonExistingFeature(): void
    {
        Feature::fake(['FEATURE_ONE'], function (): void {
            Feature::registerFeatures([
                'FEATURE_ONE' => [
                    'name' => 'Feature 1',
                    'default' => true,
                    'toggleable' => true,
                    'active' => false,
                    'major' => false,
                    'description' => 'This is a test feature',
                ],
            ]);

            $url = '/api/_action/feature-flag/enable/FEATURE_XYZ';
            $client = $this->getBrowser();
            $client->request('POST', $url);

            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            static::assertNotEmpty($response['errors'][0]);
            unset($response['errors'][0]['trace']);
            static::assertSame([
                'errors' => [
                    [
                        'status' => '400',
                        'code' => FeatureException::FEATURE_NOT_REGISTERED,
                        'title' => 'Bad Request',
                        'detail' => 'Feature "FEATURE_XYZ" is not registered.',
                        'meta' => [
                            'parameters' => [
                                'feature' => 'FEATURE_XYZ',
                            ],
                        ],
                    ],
                ],
            ], $response);
            static::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        });
    }

    public function testEnableNonToggleableFeature(): void
    {
        Feature::fake(['FEATURE_ONE'], function (): void {
            Feature::registerFeatures([
                'FEATURE_ONE' => [
                    'name' => 'Feature 1',
                    'default' => true,
                    'toggleable' => false,
                    'active' => false,
                    'major' => false,
                    'description' => 'This is a test feature',
                ],
            ]);

            $url = '/api/_action/feature-flag/enable/FEATURE_ONE';
            $client = $this->getBrowser();
            $client->request('POST', $url);

            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            static::assertNotEmpty($response['errors'][0]);
            unset($response['errors'][0]['trace']);
            static::assertSame([
                'errors' => [
                    [
                        'status' => '400',
                        'code' => FeatureException::FEATURE_CANNOT_BE_TOGGLE,
                        'title' => 'Bad Request',
                        'detail' => 'Feature "FEATURE_ONE" cannot be toggled.',
                        'meta' => [
                            'parameters' => [
                                'feature' => 'FEATURE_ONE',
                            ],
                        ],
                    ],
                ],
            ], $response);
            static::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        });
    }

    public function testDisable(): void
    {
        Feature::fake(['FEATURE_ONE'], function (): void {
            Feature::registerFeatures([
                'FEATURE_ONE' => [
                    'name' => 'Feature 1',
                    'default' => true,
                    'toggleable' => true,
                    'active' => true,
                    'description' => 'This is a test feature',
                ],
            ]);

            static::assertTrue(Feature::isActive('FEATURE_ONE'));

            $url = '/api/_action/feature-flag/disable/FEATURE_ONE';
            $client = $this->getBrowser();
            $client->request('POST', $url);

            static::assertSame('', $client->getResponse()->getContent());
            static::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
            static::assertFalse(Feature::isActive('FEATURE_ONE'));
        });
    }

    public function testLoad(): void
    {
        Feature::fake(['FEATURE_ONE'], function (): void {
            $featureFlags = [
                'FOO' => [
                    'name' => 'Foo',
                    'default' => true,
                    'toggleable' => true,
                    'active' => false,
                    'major' => true,
                    'description' => 'This is a test feature',
                ],
                'BAR' => [
                    'name' => 'Bar',
                    'default' => true,
                    'toggleable' => true,
                    'active' => false,
                    'major' => false,
                    'description' => 'This is another test feature',
                ],
            ];

            Feature::registerFeatures($featureFlags);

            $url = '/api/_action/feature-flag';
            $client = $this->getBrowser();
            $client->request('GET', $url);

            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            static::assertSame($featureFlags, $response);
            static::assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        });
    }
}
