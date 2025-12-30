<?php

declare(strict_types=1);

namespace Authza\Tests\Adapters\Cache;

use Authza\Adapters\Cache\FileCache;
use PHPUnit\Framework\TestCase;

class FileCacheTest extends TestCase
{
    private FileCache $cache;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/authza_test_cache_' . uniqid();
        $this->cache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->cacheDir);
        }
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

    public function testCacheDirectoryCreation(): void
    {
        $this->assertTrue(is_dir($this->cacheDir));
    }
}
