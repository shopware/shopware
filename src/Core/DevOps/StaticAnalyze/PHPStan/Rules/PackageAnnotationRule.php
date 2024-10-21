<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('core')]
class PackageAnnotationRule implements Rule
{
    /**
     * @internal
     */
    public const PRODUCT_AREA_MAPPING = [
        'inventory' => [
            '/Shopware\\\\Core\\\\Content\\\\(Product|ProductExport|Property)\\\\/',
            '/Shopware\\\\Core\\\\System\\\\(Currency|Unit)\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Product\\\\/',
        ],
        'content' => [
            '/Shopware\\\\Core\\\\Content\\\\(Media|Category|Cms|ContactForm|LandingPage)\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Cms\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\LandingPage\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Contact\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Navigation\\\\/',
            '/Shopware\\\\Storefront\\\\Pagelet\\\\Menu\\\\/',
            '/Shopware\\\\Storefront\\\\Pagelet\\\\Footer\\\\/',
            '/Shopware\\\\Storefront\\\\Pagelet\\\\Header\\\\/',
        ],
        'services-settings' => [
            '/Shopware\\\\.*\\\\(Rule|Flow|ProductStream)\\\\/',
            '/Shopware\\\\Core\\\\Framework\\\\(Event)\\\\/',
            '/Shopware\\\\Core\\\\System\\\\(Tag)\\\\/',
            '/Shopware\\\\Core\\\\Content\\\\(ImportExport|Mail)\\\\/',
            '/Shopware\\\\Core\\\\Framework\\\\(Update)\\\\/',
            '/Shopware\\\\Core\\\\System\\\\(Country|CustomField|Integration|Language|Locale|Snippet|User)\\\\/',
            '/Shopware\\\\Storefront\\\\Pagelet\\\\Country\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Suggest\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Search\\\\/',
            '/Shopware\\\\Core\\\\Framework\\\\Store\\\\/',
        ],
        'sales-channel' => [
            '/Shopware\\\\Core\\\\Content\\\\(MailTemplate|Seo|Sitemap)\\\\/',
            '/Shopware\\\\Core\\\\System\\\\(SalesChannel)\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Sitemap\\\\/',
            '/Shopware\\\\Storefront\\\\Pagelet\\\\Captcha\\\\/',
        ],
        'checkout' => [
            '/Shopware\\\\Core\\\\Checkout\\\\(Cart|Payment|Promotion|Shipping)\\\\/',
            '/Shopware\\\\Core\\\\Checkout\\\\(Customer|Document|Order)\\\\/',
            '/Shopware\\\\Core\\\\Content\\\\(Newsletter)\\\\/',
            '/Shopware\\\\Core\\\\System\\\\(DeliveryTime|NumberRange|StateMachine)\\\\/',
            '/Shopware\\\\Core\\\\System\\\\(DeliveryTime|Salutation|Tax)\\\\/',
            '/Shopware\\\\Storefront\\\\Checkout\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Account\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Address\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Checkout\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Maintenance\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Newsletter\\\\/',
            '/Shopware\\\\Storefront\\\\Page\\\\Wishlist\\\\/',
            '/Shopware\\\\Storefront\\\\Pagelet\\\\Newsletter\\\\/',
            '/Shopware\\\\Storefront\\\\Pagelet\\\\Wishlist\\\\/',
        ],
        'storefront' => [
            '/Shopware\\\\Storefront\\\\Theme\\\\/',
            '/Shopware\\\\Storefront\\\\Controller\\\\/',
            '/Shopware\\\\Storefront\\\\(DependencyInjection|Migration|Event|Exception|Framework|Test)\\\\/',
        ],
        'core' => [
            '/Shopware\\\\Core\\\\Framework\\\\(Adapter|Api|App|Changelog|DataAbstractionLayer|Demodata|DependencyInjection)\\\\/',
            '/Shopware\\\\Core\\\\Framework\\\\(Increment|Log|MessageQueue|Migration|Parameter|Plugin|RateLimiter|Script|Routing|Struct|Util|Uuid|Validation|Webhook)\\\\/',
            '/Shopware\\\\Core\\\\DevOps\\\\/',
            '/Shopware\\\\Core\\\\Installer\\\\/',
            '/Shopware\\\\Core\\\\Maintenance\\\\/',
            '/Shopware\\\\Core\\\\Migration\\\\/',
            '/Shopware\\\\Core\\\\Profiling\\\\/',
            '/Shopware\\\\Elasticsearch\\\\/',
            '/Shopware\\\\Docs\\\\/',
            '/Shopware\\\\Core\\\\System\\\\(Annotation|CustomEntity|DependencyInjection|SystemConfig)\\\\/',
            '/Shopware\\\\.*\\\\(DataAbstractionLayer)\\\\/',
        ],
        'administration' => [
            '/Shopware\\\\Administration\\\\/',
        ],
        'data-services' => [
            '/Shopware\\\\Core\\\\System\\\\UsageData\\\\/',
        ],
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->getClassReflection()->isAnonymous()) {
            return [];
        }

        if ($this->isTestClass($node)) {
            return [];
        }

        $area = $this->getProductArea($node);

        if ($this->hasPackageAnnotation($node)) {
            return [];
        }

        return [\sprintf('This class is missing the "#[Package(...)]" attribute (recommendation: %s)', $area ?? 'unknown')];
    }

    private function getProductArea(InClassNode $node): ?string
    {
        $namespace = $node->getClassReflection()->getName();

        foreach (self::PRODUCT_AREA_MAPPING as $area => $regexes) {
            foreach ($regexes as $regex) {
                if (preg_match($regex, $namespace)) {
                    return $area;
                }
            }
        }

        return null;
    }

    private function hasPackageAnnotation(InClassNode $class): bool
    {
        foreach ($class->getOriginalNode()->attrGroups as $group) {
            $attribute = $group->attrs[0];

            /** @var FullyQualified $name */
            $name = $attribute->name;

            if ($name->toString() === Package::class) {
                return true;
            }
        }

        return false;
    }

    private function isTestClass(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        if (\str_contains($namespace, '\\Tests\\') || \str_contains($namespace, '\\Test\\')) {
            return true;
        }

        $file = (string) $node->getClassReflection()->getFileName();
        if (\str_contains($file, '/tests/') || \str_contains($file, '/Tests/') || \str_contains($file, '/Test/')) {
            return true;
        }

        if ($node->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $node->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }
}
