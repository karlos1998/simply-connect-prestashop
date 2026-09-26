<?php
namespace SimplyConnect\PrestaShop\Tests;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimplyConnect\PrestaShop\Api\ApiClient;
use SimplyConnect\PrestaShop\Api\HttpResponse;
use SimplyConnect\PrestaShop\Api\UnknownOutcomeException;
final class ApiClientTest extends TestCase {
    public function testSendUsesStableKey(): void { $t=new FakeTransport(new HttpResponse(202,'{"messageId":"m","dispatchId":"d","commandId":"c","status":"ACCEPTED"}')); $r=(new ApiClient($t,'https://api.test','key'))->sendSms('e','+48123','Hi','stable'); self::assertSame('m',$r['messageId']); self::assertSame('stable',$t->requests[0]['headers']['Idempotency-Key']); }
    public function testPostTransportFailureIsUnknown(): void { $this->expectException(UnknownOutcomeException::class); (new ApiClient(new FakeTransport(new RuntimeException('timeout')),'https://api.test','key'))->sendSms('e','+48123','Hi','stable'); }
}
