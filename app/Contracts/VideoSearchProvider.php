<?php

namespace App\Contracts;

interface VideoSearchProvider
{
    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query): array;
}
