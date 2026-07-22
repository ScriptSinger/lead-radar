<?php

namespace Tests\Unit;

use App\Support\VkUrl;
use PHPUnit\Framework\TestCase;

class VkUrlTest extends TestCase
{
    public function test_accepts_valid_vk_urls(): void
    {
        $this->assertTrue(VkUrl::isValid('https://vk.com/v_inorse'));
        $this->assertTrue(VkUrl::isValid('https://vk.ru/club123'));
        $this->assertTrue(VkUrl::isValid('http://m.vk.com/halturaufa'));
        $this->assertTrue(VkUrl::isValid('https://www.vk.com/public1'));
    }

    public function test_rejects_invalid_urls(): void
    {
        $this->assertFalse(VkUrl::isValid(null));
        $this->assertFalse(VkUrl::isValid(''));
        $this->assertFalse(VkUrl::isValid('   '));
        $this->assertFalse(VkUrl::isValid('https://vk.com/'));
        $this->assertFalse(VkUrl::isValid('https://vk.com'));
        $this->assertFalse(VkUrl::isValid('https://example.com/group'));
        $this->assertFalse(VkUrl::isValid('ftp://vk.com/group'));
        $this->assertFalse(VkUrl::isValid('not-a-url'));
        $this->assertFalse(VkUrl::isValid('//vk.com/group'));
    }

    public function test_validation_message_is_non_empty(): void
    {
        $this->assertNotSame('', VkUrl::validationMessage());
        $this->assertStringContainsString('VK', VkUrl::validationMessage());
    }
}
