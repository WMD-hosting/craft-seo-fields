<?php

namespace studioespresso\seofields\services;

use Illuminate\Container\Attributes\Scoped;

/**
 * The elements first saved during the current request or queue job.
 *
 * Until that request ends, no visitor has been served such an element's URI, so a URI it gets and loses within the
 * same request needs no redirect. Code that creates an entry and then gives each site its own slug would otherwise
 * leave a redirect behind for every site. The instance is scoped, so each request and each queue job starts empty.
 */
#[Scoped]
class NewElements
{
    /** @var array<int, true> */
    private array $ids = [];

    public function add(int $id): void
    {
        $this->ids[$id] = true;
    }

    public function has(?int $id): bool
    {
        return $id !== null && isset($this->ids[$id]);
    }
}
