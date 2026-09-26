<?php

namespace SimplyConnect\PrestaShop\Api;

use RuntimeException;

class ApiException extends RuntimeException {
    public function __construct(string $message, public readonly string $kind, public readonly ?int $statusCode = null, public readonly ?string $problemCode = null, public readonly ?string $correlationId = null, public readonly ?int $retryAfter = null) { parent::__construct($message); }

    public static function fromResponse(HttpResponse $response): self {
        $data = json_decode($response->body, true);
        $data = is_array($data) ? $data : [];
        $kind = match (true) {
            in_array($response->status, [400, 422], true) => 'validation',
            in_array($response->status, [401, 403], true) => 'authentication',
            $response->status === 404 => 'not_found',
            $response->status === 409 => 'idempotency_conflict',
            $response->status === 429 => 'rate_limit',
            $response->status >= 500 => 'server',
            default => 'request',
        };
        $retry = $response->header('Retry-After');
        return new self(
            is_string($data['detail'] ?? null) ? $data['detail'] : 'Simply Connect request failed with HTTP ' . $response->status . '.',
            $kind,
            $response->status,
            is_string($data['code'] ?? null) ? $data['code'] : null,
            is_string($data['correlationId'] ?? null) ? $data['correlationId'] : $response->header('X-Correlation-Id'),
            is_string($retry) && ctype_digit($retry) ? (int) $retry : null
        );
    }
}
