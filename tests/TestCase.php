<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function prepareUrlForRequest($uri)
    {
        $preparedUri = parent::prepareUrlForRequest($uri);

        if (isset($this->serverVariables['HTTP_HOST'])) {
            $parsedUrl = parse_url($preparedUri);
            $scheme = $parsedUrl['scheme'] ?? 'http';
            $path = $parsedUrl['path'] ?? '';
            $query = isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '';

            return "{$scheme}://{$this->serverVariables['HTTP_HOST']}{$path}{$query}";
        }

        return $preparedUri;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
