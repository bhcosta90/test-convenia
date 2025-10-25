<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

use function in_array;
use function is_array;

final class EmployeeListCache
{
    private const string REGISTRY_PREFIX = 'employees:list:'; // base for registry and keys

    public static function key(int $userId, int $page): string
    {
        return self::REGISTRY_PREFIX.$userId.':page:'.$page;
    }

    /**
     * Remember and track the cache key inside a per-user registry so it can be invalidated later.
     *
     * @template T
     *
     * @param  Closure():T  $compute
     */
    public static function remember(int $userId, int $page, Closure $compute): mixed
    {
        $key = self::key($userId, $page);
        $ttl = (int) config('cache.employee_list_ttl');

        $value = Cache::remember($key, $ttl, $compute);

        // Track this key in the user's registry
        $registryKey = self::registryKey($userId);
        try {
            $keys = Cache::get($registryKey, []);
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
                Cache::put($registryKey, $keys, $ttl);
            }
        } catch (Throwable $e) {
            // best-effort tracking; ignore failures
        }

        return $value;
    }

    /**
     * Invalidate all cached pages for a given user id.
     */
    public static function invalidateForUserId(int $userId): void
    {
        $registryKey = self::registryKey($userId);
        $keys = Cache::pull($registryKey, []);

        if (is_array($keys)) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }

    private static function registryKey(int $userId): string
    {
        return self::REGISTRY_PREFIX.$userId.':keys';
    }
}
