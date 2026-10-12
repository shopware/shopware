<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Framework\Log\Package;

/**
 * Composes the nested label PNG for mails from the artwork in `Framework/Resources/garan`.
 *
 * @internal
 */
#[Package('inventory')]
class GaranLabelInlineImage
{
    public const DIRECTORY = __DIR__ . '/../../../Framework/Resources/garan';

    private const BASE_IMAGE = 'nested-label-base.png';

    private const NUMBERS_IMAGE = 'nested-label-numbers.png';

    private const NAME_PATTERN = '/^garan-label-nested-(\d+)\.png$/';

    private const CID_PATTERN = '/cid:(garan-label-nested-\d+\.png)/';

    /**
     * The duration field of the 390x60 artwork, see the README.
     */
    private const FIELD_X = 10;

    private const FIELD_WIDTH = 65;

    private const LABEL_HEIGHT = 60;

    /**
     * @var array<string, string>
     */
    private array $rendered = [];

    public function __construct(private readonly string $directory = self::DIRECTORY)
    {
    }

    public function getName(int $guaranteeMonths): ?string
    {
        if ($this->getRow($guaranteeMonths) === null) {
            return null;
        }

        return \sprintf('garan-label-nested-%d.png', $guaranteeMonths);
    }

    public function render(string $name): ?string
    {
        if (!preg_match(self::NAME_PATTERN, $name, $matches)) {
            return null;
        }

        $row = $this->getRow((int) $matches[1]);

        if ($row === null) {
            return null;
        }

        if (isset($this->rendered[$name])) {
            return $this->rendered[$name];
        }

        $label = @imagecreatefrompng($this->directory . '/' . self::BASE_IMAGE);
        $numbers = @imagecreatefrompng($this->directory . '/' . self::NUMBERS_IMAGE);

        if (!$label || !$numbers) {
            return null;
        }

        imagecopy($label, $numbers, self::FIELD_X, 0, 0, $row * self::LABEL_HEIGHT, self::FIELD_WIDTH, self::LABEL_HEIGHT);

        ob_start();
        $rendered = imagepng($label);
        $png = ob_get_clean();

        if (!$rendered || !\is_string($png)) {
            return null;
        }

        return $this->rendered[$name] = $png;
    }

    /**
     * @return list<string>
     */
    public function findReferencedNames(string $html): array
    {
        preg_match_all(self::CID_PATTERN, $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function getRow(int $guaranteeMonths): ?int
    {
        if (!GaranLabelProductValidator::isValidDuration($guaranteeMonths)) {
            return null;
        }

        return intdiv($guaranteeMonths - GaranLabelProductValidator::MINIMUM_MONTHS, GaranLabelProductValidator::STEP_MONTHS);
    }
}
