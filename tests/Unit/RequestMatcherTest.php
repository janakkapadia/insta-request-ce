<?php

namespace Tests\Unit;

use App\Domains\ImportExport\Services\RequestMatcher;
use PHPUnit\Framework\TestCase;

class RequestMatcherTest extends TestCase
{
    public function test_normalize_url_strips_query_string_and_fragment(): void
    {
        $url = 'https://api.example.com/v1/sites/123/backups?page=1&limit=10#section';
        $normalized = RequestMatcher::normalizeUrl($url);

        $this->assertEquals('api.example.com/v1/sites/123/backups', $normalized);
    }

    public function test_normalize_url_strips_various_schemes(): void
    {
        $this->assertEquals('api.example.com/test', RequestMatcher::normalizeUrl('http://api.example.com/test'));
        $this->assertEquals('api.example.com/test', RequestMatcher::normalizeUrl('https://api.example.com/test'));
        $this->assertEquals('api.example.com/test', RequestMatcher::normalizeUrl('ws://api.example.com/test'));
        $this->assertEquals('api.example.com/test', RequestMatcher::normalizeUrl('//api.example.com/test'));
    }

    public function test_normalize_url_trims_trailing_slashes_and_lowercases(): void
    {
        $this->assertEquals('api.example.com/sites', RequestMatcher::normalizeUrl('https://API.EXAMPLE.COM/SITES/'));
    }

    public function test_normalize_url_handles_empty_or_null(): void
    {
        $this->assertEquals('', RequestMatcher::normalizeUrl(''));
        $this->assertEquals('', RequestMatcher::normalizeUrl(null));
        $this->assertEquals('', RequestMatcher::normalizeUrl('   '));
    }

    public function test_make_key_prioritizes_method_and_normalized_url(): void
    {
        $key = RequestMatcher::makeKey('List sites', 'get', 'https://api.example.com/sites');
        $this->assertEquals('GET::api.example.com/sites', $key);
    }

    public function test_make_key_falls_back_to_name_and_method_when_url_empty(): void
    {
        $key = RequestMatcher::makeKey('My cURL Request', 'POST', '');
        $this->assertEquals('my curl request::POST', $key);

        $keyNull = RequestMatcher::makeKey('My cURL Request', 'POST', null);
        $this->assertEquals('my curl request::POST', $keyNull);
    }

    public function test_make_key_prevents_collision_for_same_summary_endpoints(): void
    {
        $key1 = RequestMatcher::makeKey('Get backups', 'GET', 'https://api.example.com/sites/{id}/backups');
        $key2 = RequestMatcher::makeKey('Get backups', 'GET', 'https://api.example.com/teams/{id}/backups');

        $this->assertNotEquals($key1, $key2);
        $this->assertEquals('GET::api.example.com/sites/{id}/backups', $key1);
        $this->assertEquals('GET::api.example.com/teams/{id}/backups', $key2);
    }

    public function test_make_key_remains_stable_when_summary_is_edited(): void
    {
        $keyBefore = RequestMatcher::makeKey('List sites', 'GET', 'https://api.example.com/sites');
        $keyAfter = RequestMatcher::makeKey('List all sites', 'GET', 'https://api.example.com/sites');

        $this->assertEquals($keyBefore, $keyAfter);
        $this->assertEquals('GET::api.example.com/sites', $keyBefore);
    }
}
