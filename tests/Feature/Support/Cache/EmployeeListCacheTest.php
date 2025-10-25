<?php

declare(strict_types=1);

use App\Support\Cache\EmployeeListCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('builds deterministic keys per user and page', function () {
    expect(EmployeeListCache::key(10, 1))->toBe('employees:list:10:page:1')
        ->and(EmployeeListCache::key(10, 2))->toBe('employees:list:10:page:2')
        ->and(EmployeeListCache::key(11, 1))->toBe('employees:list:11:page:1');
});

it('remembers computed value and returns cached value on subsequent calls', function () {
    $userId = 42;
    $page = 3;
    $key = EmployeeListCache::key($userId, $page);

    $calls = 0;
    $value1 = EmployeeListCache::remember($userId, $page, function () use (&$calls) {
        $calls++;

        return ['from' => 'compute', 'time' => now()->timestamp];
    });

    // First call should compute and store
    expect($calls)->toBe(1)
        ->and(Cache::has($key))->toBeTrue();

    // Second call should use cache, not recompute
    $value2 = EmployeeListCache::remember($userId, $page, function () use (&$calls) {
        $calls++;

        return ['from' => 'compute-again'];
    });

    expect($calls)->toBe(1) // still one compute
        ->and($value2)->toBe($value1); // exact same payload
});

it('tracks keys in a per-user registry so we can invalidate them later', function () {
    $userId = 7;

    // generate 2 different pages
    EmployeeListCache::remember($userId, 1, fn () => ['p' => 1]);
    EmployeeListCache::remember($userId, 2, fn () => ['p' => 2]);

    $registryKey = 'employees:list:'.$userId.':keys';
    $keys = Cache::get($registryKey);

    expect($keys)->toBeArray()
        ->and($keys)->toContain(EmployeeListCache::key($userId, 1))
        ->and($keys)->toContain(EmployeeListCache::key($userId, 2));
});

it('invalidateForUserId forgets all cached pages for that user and clears registry', function () {
    $userId = 55;

    // Arrange cached pages
    EmployeeListCache::remember($userId, 1, fn () => ['data' => 'p1']);
    EmployeeListCache::remember($userId, 2, fn () => ['data' => 'p2']);

    $key1 = EmployeeListCache::key($userId, 1);
    $key2 = EmployeeListCache::key($userId, 2);
    $registryKey = 'employees:list:'.$userId.':keys';

    expect(Cache::has($key1))->toBeTrue()
        ->and(Cache::has($key2))->toBeTrue()
        ->and(Cache::has($registryKey))->toBeTrue();

    // Act
    EmployeeListCache::invalidateForUserId($userId);

    // Assert deletion
    expect(Cache::has($key1))->toBeFalse()
        ->and(Cache::has($key2))->toBeFalse()
        ->and(Cache::has($registryKey))->toBeFalse();
});

it('does not affect other users cache when invalidating a specific user', function () {
    $userA = 1;
    $userB = 2;
    EmployeeListCache::remember($userA, 1, fn () => ['A1']);
    EmployeeListCache::remember($userA, 2, fn () => ['A2']);
    EmployeeListCache::remember($userB, 1, fn () => ['B1']);

    $a1 = EmployeeListCache::key($userA, 1);
    $a2 = EmployeeListCache::key($userA, 2);
    $b1 = EmployeeListCache::key($userB, 1);

    expect(Cache::has($a1))->toBeTrue()
        ->and(Cache::has($a2))->toBeTrue()
        ->and(Cache::has($b1))->toBeTrue();

    // Invalidate only user A
    EmployeeListCache::invalidateForUserId($userA);

    expect(Cache::has($a1))->toBeFalse()
        ->and(Cache::has($a2))->toBeFalse()
        ->and(Cache::has($b1))->toBeTrue();
});

it('handles missing or malformed registry gracefully on invalidate', function () {
    $userId = 999;

    // No registry created yet; should not throw and should be a no-op
    EmployeeListCache::invalidateForUserId($userId);

    // Create a malformed registry (not an array) and ensure it still does not throw
    Cache::put('employees:list:'.$userId.':keys', 'oops', 60);
    EmployeeListCache::invalidateForUserId($userId);

    // If we got here, no exceptions were thrown; also confirm the registry was removed
    expect(Cache::has('employees:list:'.$userId.':keys'))->toBeFalse();
});

it('gracefully ignores exceptions during registry tracking in remember', function () {
    $userId = 123;
    $page = 1;

    // Simulate cache layer throwing while fetching the registry key, but ensure remember still returns a value
    $mock = Cache::partialMock();
    $mock->shouldReceive('remember')->once()->andReturn(['ok' => true]);
    $mock->shouldReceive('get')->once()->andThrow(new RuntimeException('boom'));
    // When tracking fails, we must NOT attempt to put the registry key
    $mock->shouldReceive('put')->never();

    $value = EmployeeListCache::remember($userId, $page, fn () => ['ok' => true]);

    expect($value)->toBe(['ok' => true]);
});
