<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PHPStan\TrinaryLogic;
use PHPStan\Type\AcceptsResult;
use PHPStan\Type\IsSuperTypeOfResult;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class BrandedStringType extends StringType
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $brand,
        private readonly ?TrinaryLogic $nonEmptyStringLogic = null,
        private readonly ?TrinaryLogic $nonFalsyStringLogic = null,
    ) {
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function describe(VerbosityLevel $level): string
    {
        return $this->brand;
    }

    public function isSuperTypeOf(Type $type): IsSuperTypeOfResult
    {
        if ($type instanceof self && $type->brand === $this->brand) {
            return IsSuperTypeOfResult::createYes();
        }

        return IsSuperTypeOfResult::createNo();
    }

    public function accepts(Type $type, bool $strictTypes): AcceptsResult
    {
        return $this->isSuperTypeOf($type)->toAcceptsResult();
    }

    public function isNonEmptyString(): TrinaryLogic
    {
        return $this->nonEmptyStringLogic ?? TrinaryLogic::createMaybe();
    }

    public function isNonFalsyString(): TrinaryLogic
    {
        return $this->nonFalsyStringLogic ?? TrinaryLogic::createMaybe();
    }

    public function isClassString(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }
}
