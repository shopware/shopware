<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig parses calls in inactive template branches before evaluating feature flags.
 * Keep removed function names registered in major mode so those templates compile.
 *
 * This currently covers functions only. Removing extensions that provide filters,
 * tests, operators, or token parsers may require matching compatibility definitions.
 *
 * @internal
 */
#[Package('framework')]
class CompatTwigExtension extends AbstractExtension
{
    /**
     * @var array<string, list<string>>
     */
    public const FUNCTIONS_BY_FEATURE = [
        'v6.8.0.0' => [
            'category_url',
            'category_linknewtab',
            'sw_breadcrumb_full',
            'sw_breadcrumb_full_by_id',
        ],
    ];

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        $functions = [];

        foreach (self::FUNCTIONS_BY_FEATURE as $flag => $names) {
            if (!Feature::isActive($flag)) {
                continue;
            }

            foreach ($names as $name) {
                $functions[] = new TwigFunction($name, static function () use ($flag, $name): never {
                    throw AdapterException::invalidArgument(\sprintf('Twig function "%s" was removed with feature "%s".', $name, $flag));
                });
            }
        }

        return $functions;
    }
}
