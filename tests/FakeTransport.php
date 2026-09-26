<?php
namespace SimplyConnect\PrestaShop\Tests;
use RuntimeException;
use SimplyConnect\PrestaShop\Api\HttpResponse;
use SimplyConnect\PrestaShop\Api\Transport;
final class FakeTransport implements Transport {
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?array}> */ public array $requests=[];
    public function __construct(private readonly HttpResponse|RuntimeException $result) {}
    public function request(string $method,string $url,array $headers,?array $body=null): HttpResponse { $this->requests[]=compact('method','url','headers','body'); if($this->result instanceof RuntimeException){throw $this->result;} return $this->result; }
}
