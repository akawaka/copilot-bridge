<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class AuthService
{
    public function __construct(
        private CacheInterface $cache,
        #[Autowire(param: 'copilot.auth_token_expiration')]
        private int $authTokenExpiration,
    ) {
    }

    /**
     * Store authentication data
     */
    public function set(string $key, array $data): void
    {
        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($data) {
            $item->expiresAfter(3600 * 24 * $this->authTokenExpiration);
            return $data;
        });
    }

    /**
     * Retrieve authentication data
     */
    public function get(string $key): ?array
    {
        try {
            return $this->cache->get($key, static function (ItemInterface $item, bool &$save) {
                $save = false; // Prevent caching the fallback value on misses
                return null;
            });
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Remove authentication data
     */
    public function remove(string $key): void
    {
        $this->cache->delete($key);
    }
}