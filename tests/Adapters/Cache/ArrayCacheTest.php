<?php

declare(strict_types=1);

namespace Authza\Tests\Adapters\Cache;

use Authza\Adapters\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;

class ArrayCacheTest extends TestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    public function testSetAndGet(): void
    {
        $this->assertTrue($this->cache->set('key1', 'value1'));
        $this->assertEquals('value1', $this->cache->get('key1'));
    }

    public function testGetWithDefault(): void
    {
        $this->assertEquals('default', $this->cache->get('nonexistent', 'default'));
    }

    public function testHas(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->delete('key1'));
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testTtlExpiration(): void
    {
        $this->cache->set('key1', 'value1', 1);
        $this->assertTrue($this->cache->has('key1'));
        sleep(2);
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        $values = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');
        $values = iterator_to_array($values);
        
        $this->assertEquals('value1', $values['key1']);
        $this->assertEquals('value2', $values['key2']);
        $this->assertEquals('default', $values['key3']);
    }

    public function testSetMultiple(): void
    {
        $this->assertTrue($this->cache->setMultiple(['key1' => 'value1', 'key2' => 'value2']));
        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertEquals('value2', $this->cache->get('key2'));
    }

    public function testDeleteMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key2']));
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }
}
