<?php
namespace SimplyConnect\PrestaShop\Tests;
use PHPUnit\Framework\TestCase;
use SimplyConnect\PrestaShop\TemplateRenderer;
final class TemplateRendererTest extends TestCase { public function testItSanitizesValues(): void { self::assertSame('Hi Jan, A B',(new TemplateRenderer())->render('Hi {customer_first_name}, {order_reference}',['customer_first_name'=>'<b>Jan</b>','order_reference'=>"A\nB"])); } }
