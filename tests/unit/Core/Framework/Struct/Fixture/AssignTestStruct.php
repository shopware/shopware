<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[\AllowDynamicProperties]
class AssignTestStruct extends Struct
{
    use EntityIdTrait;

    protected string $_uniqueIdentifier;

    /**
     * @var mixed
     */
    // @phpstan-ignore shopware.propertyNativeType
    protected $noType;

    protected ?int $int = null;

    protected ?float $float = null;

    protected ?string $string = null;

    protected string $notNullableString;

    protected ?bool $bool = null;

    /**
     * @var array<mixed>|null
     */
    protected ?array $array = null;

    protected ?\DateTimeInterface $date = null;

    protected ?\stdClass $stdClass = null;

    protected ?Struct $struct = null;

    protected ?AssignTestStruct $assignTestStruct = null;

    protected string|\stdClass|AssignTestStruct|null $mixedType = null;

    // @phpstan-ignore missingType.generics
    protected ?Collection $collection = null;

    protected ?AssignTestCollection $assignCollection = null;

    // @phpstan-ignore missingType.generics
    protected AssignTestCollection|EntityCollection|null $doubleTypeCollection = null;

    protected (\JsonSerializable&\Countable)|null $intersectionType = null;

    /**
     * Just to test creating an instance without constructor
     *
     * @param array<mixed> $data
     */
    public function __construct(
        private readonly array $data
    ) {
    }

    public function setNoType(mixed $value): void
    {
        $this->noType = $value;
    }

    public function setInt(?int $value): void
    {
        if ($value !== null) {
            ++$value;
        }

        $this->int = $value;
    }

    public function setFloat(?float $value): void
    {
        $this->float = $value;
    }

    public function setString(?string $value): void
    {
        $this->string = $value;
    }

    public function setNotNullableString(string $value): void
    {
        $this->notNullableString = $value;
    }

    /**
     * @param array<mixed>|null $value
     */
    public function setArray(?array $value): void
    {
        $this->array = $value;
    }

    public function setDate(?\DateTimeInterface $value): void
    {
        $this->date = $value;
    }

    public function setStdClass(\stdClass $value): void
    {
        $this->stdClass = $value;
    }

    public function setStruct(?Struct $value): void
    {
        $this->struct = $value;
    }

    public function setAssignTestStruct(?AssignTestStruct $value): void
    {
        $this->assignTestStruct = $value;
    }

    public function setMixedType(string|\stdClass|AssignTestStruct|null $value): void
    {
        $this->mixedType = $value;
    }

    // @phpstan-ignore missingType.generics
    public function setCollection(?Collection $value): void
    {
        $this->collection = $value;
    }

    public function setAssignTestCollection(?AssignTestCollection $value): void
    {
        $this->assignCollection = $value;
    }

    // @phpstan-ignore missingType.generics
    public function setDoubleTypeCollection(AssignTestCollection|EntityCollection|null $value): void
    {
        $this->doubleTypeCollection = $value;
    }

    public function setIntersectionType((\JsonSerializable&\Countable)|null $value): void
    {
        $this->intersectionType = $value;
    }

    public function getNoType(): mixed
    {
        return $this->noType;
    }

    public function getInt(): ?int
    {
        return $this->int;
    }

    public function getFloat(): ?float
    {
        return $this->float;
    }

    public function getString(): ?string
    {
        return $this->string;
    }

    public function getNotNullableString(): string
    {
        return $this->notNullableString;
    }

    public function getBool(): ?bool
    {
        return $this->bool;
    }

    /**
     * @return array<mixed>|null
     */
    public function getArray(): ?array
    {
        return $this->array;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function getStdClass(): ?\stdClass
    {
        return $this->stdClass;
    }

    public function getStruct(): ?Struct
    {
        return $this->struct;
    }

    public function getAssignTestStruct(): ?AssignTestStruct
    {
        return $this->assignTestStruct;
    }

    public function getMixedType(): string|\stdClass|AssignTestStruct|null
    {
        return $this->mixedType;
    }

    // @phpstan-ignore missingType.generics
    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function getAssignCollection(): ?AssignTestCollection
    {
        return $this->assignCollection;
    }

    // @phpstan-ignore missingType.generics
    public function getDoubleTypeCollection(): AssignTestCollection|EntityCollection|null
    {
        return $this->doubleTypeCollection;
    }

    public function getIntersectionType(): (\JsonSerializable&\Countable)|null
    {
        return $this->intersectionType;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
