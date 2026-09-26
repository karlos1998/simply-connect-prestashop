<?php

namespace SimplyConnect\PrestaShop\Api;

final class HttpResponse {
    /** @param array<string,string> $headers */
    public function __construct(public readonly int $status, public readonly string $body, public readonly array $headers = []) {}

    public function header(string $name): ?string {
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) { return $value; }
        }
        return null;
    }
}
