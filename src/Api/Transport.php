<?php

namespace SimplyConnect\PrestaShop\Api;

interface Transport {
    /** @param array<string,string> $headers @param array<string,mixed>|null $body */
    public function request(string $method, string $url, array $headers, ?array $body = null): HttpResponse;
}
