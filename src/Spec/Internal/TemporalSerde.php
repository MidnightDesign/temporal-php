<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal;

/** @internal */
trait TemporalSerde
{
    abstract public function toString(): string;

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /** @psalm-api */
    public function toJSON(): string
    {
        return $this->toString();
    }

    /**
     * @param string|array<array-key, mixed>|null $locales
     * @param array<array-key, mixed>|object|null $options
     * @psalm-api
     * @psalm-suppress UnusedParam
     */
    public function toLocaleString(string|array|null $locales = null, array|object|null $options = null): string
    {
        return $this->toString();
    }
}
