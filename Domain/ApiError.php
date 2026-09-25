<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

/**
 * An API failure a client can tell apart by its code, not by parsing text:
 *
 *   400 {"error": "Missing query parameter project.", "code": "missing_project"}
 *   404 {"error": "No active engagement ...", "code": "no_engagement"}
 */
final class ApiError extends \RuntimeException
{
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;

    public const MISSING_PROJECT = 'missing_project';
    public const INVALID_PROJECT = 'invalid_project';
    public const UNKNOWN_PROJECT = 'unknown_project';
    public const INVALID_USER = 'invalid_user';
    public const UNKNOWN_USER = 'unknown_user';
    public const INVALID_DATE = 'invalid_date';
    public const NO_ENGAGEMENT = 'no_engagement';
    public const UNKNOWN_ENGAGEMENT = 'unknown_engagement';
    public const INVALID_JSON = 'invalid_json';
    public const INVALID_VALUE = 'invalid_value';
    public const FORBIDDEN = 'forbidden';

    private function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $errorCode, string $message): self
    {
        return new self(self::HTTP_BAD_REQUEST, $errorCode, $message);
    }

    public static function notFound(string $errorCode, string $message): self
    {
        return new self(self::HTTP_NOT_FOUND, $errorCode, $message);
    }

    public static function forbidden(string $message): self
    {
        return new self(self::HTTP_FORBIDDEN, self::FORBIDDEN, $message);
    }

    /**
     * @return array{error: string, code: string}
     */
    public function body(): array
    {
        return ['error' => $this->getMessage(), 'code' => $this->errorCode];
    }
}
