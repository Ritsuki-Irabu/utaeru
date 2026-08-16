<?php

namespace App\Contracts;

interface MusicSearchProvider
{
    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, ?string $field = null): array;

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $providerKey): array;
}
