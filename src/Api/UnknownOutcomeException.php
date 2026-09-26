<?php

namespace SimplyConnect\PrestaShop\Api;

use RuntimeException;
use Throwable;

final class UnknownOutcomeException extends RuntimeException {
    public function __construct(public readonly string $idempotencyKey, ?Throwable $previous = null) { parent::__construct('The SMS request lost its response. Reconcile or retry only with the same idempotency key.', 0, $previous); }
}
