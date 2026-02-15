<?php declare(strict_types=1);
/**
 * File:     Attribute.php
 * Category: -
 * Author:   M. Goldenbaum
 * Created:  01.01.21 20:17
 * Updated:  -
 *
 * Description:
 *  -
 */

namespace Yai\Ymap\Connection\ConnectionLibrary;

use ArrayAccess;
use DateTimeImmutable;
use function array_key_exists;
use function array_map;
use function end;
use function get_class;
use function implode;
use function in_array;
use function is_array;
use function is_null;
use function is_object;
use function method_exists;
use function reset;

/**
 * Class Attribute
 *
 * @package Yai\Ymap\Connection\ConnectionLibrary
 * @implements ArrayAccess<int|string, mixed>
 */
class Attribute implements ArrayAccess {

    /** @var string $name */
    protected string $name;

    /**
     * Value holder
     *
     * @var array<int|string, mixed> $values
     */
    protected array $values = [];

    /**
     * Attribute constructor.
     * @param string $name
     * @param mixed|null $value
     */
    public function __construct(string $name, mixed $value = null) {
        $this->setName($name);
        $this->add($value);
    }

    /**
     * Handle class invocation calls
     *
     * @return array<int|string, mixed>|string
     */
    public function __invoke(): array|string {
        if ($this->count() > 1) {
            return $this->toArray();
        }
        return $this->toString();
    }

    /**
     * Return the serialized address
     *
     * @return array<int|string, mixed>
     */
    public function __serialize(): array {
        return $this->values;
    }

    /**
     * Return the stringified attribute
     *
     * @return string
     */
    public function __toString() {
        $stringValues = array_map(function($value) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }
            if (is_object($value) && method_exists($value, '__toString')) {
                return (string)$value;
            }
            if (is_object($value)) {
                return get_class($value);
            }
            return (string)$value;
        }, $this->values);

        return implode(", ", $stringValues);
    }

    /**
     * Return the stringified attribute
     *
     * @return string
     */
    public function toString(): string {
        return $this->__toString();
    }

    /**
     * Convert instance to array
     *
     * @return array<int|string, mixed>
     */
    public function toArray(): array {
        return $this->__serialize();
    }

    /**
     * Convert first value to a date object
     *
     * @return \DateTimeImmutable
     */
    public function toDate(): \DateTimeImmutable {
        $date = $this->first();
        if ($date instanceof \DateTimeImmutable) return $date;
        if ($date instanceof \DateTimeInterface) return \DateTimeImmutable::createFromInterface($date);

        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception $e) {
            return new \DateTimeImmutable(); // Return now as fallback? Or throw?
        }
    }

    /**
     * Determine if a value exists at a given key.
     *
     * @param int|string $key
     * @return bool
     */
    public function has(mixed $key = 0): bool {
        return array_key_exists($key, $this->values);
    }

    /**
     * Determine if a value exists at a given key.
     *
     * @param int|string $key
     * @return bool
     */
    public function exist(mixed $key = 0): bool {
        return $this->has($key);
    }

    /**
     * Check if the attribute contains the given value
     * @param mixed $value
     *
     * @return bool
     */
    public function contains(mixed $value): bool {
        return in_array($value, $this->values, true);
    }

    /**
     * Get a value by a given key.
     *
     * @param int|string $key
     * @return mixed
     */
    public function get(int|string $key = 0): mixed {
        return $this->values[$key] ?? null;
    }

    /**
     * Set the value by a given key.
     *
     * @param mixed $key
     * @param mixed $value
     * @return Attribute
     */
    public function set(mixed $value, mixed $key = 0): Attribute {
        if (is_null($key)) {
            $this->values[] = $value;
        } else {
            $this->values[$key] = $value;
        }
        return $this;
    }

    /**
     * Unset a value by a given key.
     *
     * @param int|string $key
     * @return Attribute
     */
    public function remove(int|string $key = 0): Attribute {
        if (isset($this->values[$key])) {
            unset($this->values[$key]);
        }
        return $this;
    }

    /**
     * Add one or more values to the attribute
     * @param array|mixed $value
     * @param boolean $strict
     *
     * @return Attribute
     */
    public function add(mixed $value, bool $strict = false): Attribute {
        if (is_array($value)) {
            return $this->merge($value, $strict);
        }elseif ($value !== null) {
            $this->attach($value, $strict);
        }

        return $this;
    }

    /**
     * Merge a given array of values with the current values array
     * @param array<mixed> $values
     * @param boolean $strict
     *
     * @return Attribute
     */
    public function merge(array $values, bool $strict = false): Attribute {
        foreach ($values as $value) {
            $this->attach($value, $strict);
        }

        return $this;
    }

    /**
     * Attach a given value to the current value array
     * @param mixed $value
     * @param bool $strict
     * @return Attribute
     */
    public function attach(mixed $value, bool $strict = false): Attribute {
        if ($strict === true) {
            if ($this->contains($value) === false) {
                $this->values[] = $value;
            }
        }else{
            $this->values[] = $value;
        }
        return $this;
    }

    /**
     * Set the attribute name
     * @param string $name
     *
     * @return Attribute
     */
    public function setName(string $name): Attribute {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the attribute name
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Get all values
     *
     * @return array<int|string, mixed>
     */
    public function all(): array {
        reset($this->values);
        return $this->values;
    }

    /**
     * Get the first value if possible
     *
     * @return mixed|null
     */
    public function first(): mixed {
        return reset($this->values);
    }

    /**
     * Get the last value if possible
     *
     * @return mixed|null
     */
    public function last(): mixed {
        return end($this->values);
    }

    /**
     * Get the number of values
     *
     * @return int
     */
    public function count(): int {
        return count($this->values);
    }

    /**
     * @see  ArrayAccess::offsetExists
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool {
        return $this->has($offset);
    }

    /**
     * @see  ArrayAccess::offsetGet
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed {
        return $this->get($offset);
    }

    /**
     * @see  ArrayAccess::offsetSet
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        $this->set($value, $offset);
    }

    /**
     * @see  ArrayAccess::offsetUnset
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void {
        $this->remove($offset);
    }

    /**
     * @param callable $callback
     * @return array<int|string, mixed>
     */
    public function map(callable $callback): array {
        return array_map($callback, $this->values);
    }
}
