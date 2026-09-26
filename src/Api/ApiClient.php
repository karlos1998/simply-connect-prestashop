<?php

namespace SimplyConnect\PrestaShop\Api;

use RuntimeException;

final class ApiClient {
    public function __construct(private readonly Transport $transport, private readonly string $baseUrl, private readonly string $apiKey) {
        if (trim($baseUrl) === '' || trim($apiKey) === '') { throw new RuntimeException('The Simply Connect API URL and API key are required.'); }
    }

    /** @return list<array{id:string,name:string,phoneNumber:?string,enabled:bool}> */
    public function endpoints(): array {
        $data = $this->decode($this->send('GET', '/api/v1/external/endpoints'));
        if (!array_is_list($data)) { throw new ApiException('Invalid endpoint list.', 'server'); }
        $result = [];
        foreach ($data as $item) {
            if (!is_array($item) || !is_string($item['id'] ?? null) || !is_string($item['name'] ?? null)) { throw new ApiException('Invalid endpoint item.', 'server'); }
            $result[] = ['id' => $item['id'], 'name' => $item['name'], 'phoneNumber' => is_string($item['phoneNumber'] ?? null) ? $item['phoneNumber'] : null, 'enabled' => ($item['enabled'] ?? false) === true];
        }
        return $result;
    }

    /** @return array{messageId:string,dispatchId:string,commandId:string,status:string,correlationId:?string} */
    public function sendSms(string $endpointId, string $to, string $body, string $idempotencyKey): array {
        try { $response = $this->send('POST', '/api/v1/external/messages', ['endpointId' => $endpointId, 'to' => $to, 'body' => $body], ['Idempotency-Key' => $idempotencyKey]); }
        catch (RuntimeException $exception) { if ($exception instanceof ApiException) { throw $exception; } throw new UnknownOutcomeException($idempotencyKey, $exception); }
        $data = $this->decode($response);
        foreach (['messageId', 'dispatchId', 'commandId', 'status'] as $field) { if (!is_string($data[$field] ?? null)) { throw new ApiException('Invalid SMS receipt.', 'server'); } }
        return $data + ['correlationId' => $response->header('X-Correlation-Id')];
    }

    /** @return array<string,mixed> */
    public function message(string $messageId): array { return $this->decode($this->send('GET', '/api/v1/external/messages/' . rawurlencode($messageId))); }

    /** @param array<string,mixed>|null $body @param array<string,string> $extra */
    private function send(string $method, string $path, ?array $body = null, array $extra = []): HttpResponse {
        $response = $this->transport->request($method, rtrim($this->baseUrl, '/') . $path, array_merge(['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-API-Key' => $this->apiKey, 'X-Correlation-Id' => self::uuid()], $extra), $body);
        if ($response->status < 200 || $response->status >= 300) { throw ApiException::fromResponse($response); }
        return $response;
    }

    /** @return array<mixed> */
    private function decode(HttpResponse $response): array { $data = json_decode($response->body, true); if (!is_array($data)) { throw new ApiException('Simply Connect returned malformed JSON.', 'server'); } return $data; }
    private static function uuid(): string { $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
}
