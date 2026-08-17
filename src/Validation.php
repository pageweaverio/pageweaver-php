<?php

namespace PageWeaver;

/**
 * Lightweight client-side request validation: catch shape mistakes (a missing required field, a
 * blank id interpolated into a URL, a body that isn't an array) before spending a network round
 * trip. This deliberately does NOT re-implement the API's business rules or JSON Schema validation
 * — the API remains the source of truth for those, and stays authoritative on anything not checked
 * here. It only guards against the class of mistake that produces a confusing generic 400/404 or,
 * worse, a request sent to the wrong URL (e.g. `/v1/objects/`).
 *
 * @internal
 */
final class Validation
{
    /** A non-empty string used as a path segment (an id, a slug, an env name, ...). */
    public static function requireId(?string $value, string $name): string
    {
        if ($value === null || trim($value) === '') {
            throw new PageWeaverInvalidRequestException(
                "`{$name}` is required and must be a non-empty string.",
                $name
            );
        }
        return $value;
    }

    /** A finite, positive integer (a version number, a chunk index + 1, a page seq, ...). */
    public static function requirePositiveInt($value, string $name): int
    {
        if (!is_int($value) || $value < 1) {
            throw new PageWeaverInvalidRequestException("`{$name}` must be a positive integer.", $name);
        }
        return $value;
    }

    /** A non-negative integer (a chunk index, ...). */
    public static function requireNonNegativeInt($value, string $name): int
    {
        if (!is_int($value) || $value < 0) {
            throw new PageWeaverInvalidRequestException("`{$name}` must be a non-negative integer.", $name);
        }
        return $value;
    }

    /**
     * A plain associative-array body (not a list, not null, not a scalar).
     *
     * @param mixed $value
     * @return array<string,mixed>
     */
    public static function requireObjectBody($value, string $name): array
    {
        if (!is_array($value) || self::isList($value)) {
            throw new PageWeaverInvalidRequestException("`{$name}` must be an object (associative array).", $name);
        }
        return $value;
    }

    /** A required, non-empty string field on a params array. */
    public static function requireString($value, string $name): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new PageWeaverInvalidRequestException(
                "`{$name}` is required and must be a non-empty string.",
                $name
            );
        }
        return $value;
    }

    /**
     * A required array with at least one item.
     *
     * @param mixed $value
     * @return array<int,mixed>
     */
    public static function requireNonEmptyArray($value, string $name): array
    {
        if (!is_array($value) || count($value) === 0) {
            throw new PageWeaverInvalidRequestException("`{$name}` must be a non-empty array.", $name);
        }
        return $value;
    }

    /** Assert that exactly one of two mutually-exclusive optional fields is set. */
    public static function requireOneOf($a, string $aName, $b, string $bName): void
    {
        $hasA = $a !== null && $a !== '';
        $hasB = $b !== null && $b !== '';
        if ($hasA === $hasB) {
            throw new PageWeaverInvalidRequestException("Provide exactly one of `{$aName}` or `{$bName}`.");
        }
    }

    /**
     * @param array<mixed> $value
     */
    private static function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }
        $i = 0;
        foreach ($value as $k => $_) {
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }
}
