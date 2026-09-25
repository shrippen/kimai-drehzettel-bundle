<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

/**
 * Parses API query values. Bad syntax is a 400; whether an id exists is
 * the caller's 404.
 *
 *   project=12       -> 12
 *   project=abc      -> 400 invalid_project
 *   date=2025-02-30  -> 400 invalid_date
 */
final class ApiQuery
{
    private const DATE_FORMAT = 'Y-m-d';

    public static function projectId(mixed $value): int
    {
        if ($value === null || $value === '') {
            throw ApiError::badRequest(ApiError::MISSING_PROJECT, 'Missing query parameter project.');
        }

        return self::id($value) ?? throw ApiError::badRequest(ApiError::INVALID_PROJECT, 'project must be a positive integer.');
    }

    // Null means the token owner.
    public static function userId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::id($value) ?? throw ApiError::badRequest(ApiError::INVALID_USER, 'user must be a positive integer.');
    }

    // Missing uses $default. '!' zeroes the time; the round trip rejects 2025-02-30.
    public static function date(mixed $value, \DateTimeImmutable $default): \DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = is_string($value) ? \DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value) : false;
        if ($parsed === false || $parsed->format(self::DATE_FORMAT) !== $value) {
            throw ApiError::badRequest(ApiError::INVALID_DATE, 'Invalid date, expected YYYY-MM-DD.');
        }

        return $parsed;
    }

    private static function id(mixed $value): ?int
    {
        $id = is_string($value) || is_int($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

        return $id === false ? null : $id;
    }
}
