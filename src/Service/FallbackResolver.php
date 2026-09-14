<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LandingRepository;
use App\Repository\TestimonialRepository;

/**
 * Decides whether a landing shows its own testimonials or inherits the English
 * master set.
 *
 * The rule is deliberately "any local row, including inactive ones". A country
 * that has local rows but has deactivated them all shows an empty list rather
 * than silently falling back to English, which would otherwise make it
 * impossible to intentionally show nothing.
 */
class FallbackResolver
{
    private LandingRepository $landings;

    private TestimonialRepository $testimonials;

    public function __construct(LandingRepository $landings, TestimonialRepository $testimonials)
    {
        $this->landings = $landings;
        $this->testimonials = $testimonials;
    }

    /**
     * @return array{landing:array, source:array, inherited:bool}
     */
    public function resolve(array $landing): array
    {
        $ownCount = $this->testimonials->countForLanding($landing['id']);

        if ($ownCount > 0 || $landing['is_master']) {
            return ['landing' => $landing, 'source' => $landing, 'inherited' => false];
        }

        $master = $this->landings->masterFor($landing['product_id']);

        if ($master === null || $master['id'] === $landing['id']) {
            return ['landing' => $landing, 'source' => $landing, 'inherited' => false];
        }

        return ['landing' => $landing, 'source' => $master, 'inherited' => true];
    }

    public function metadata(array $landing): array
    {
        $resolved = $this->resolve($landing);
        $source = $resolved['source'];

        $local = $this->testimonials->countForLanding($landing['id']);
        $effective = $resolved['inherited']
            ? count($this->testimonials->allForLanding($source['id'], true))
            : $local;

        return [
            'inherited' => $resolved['inherited'],
            'source_landing_id' => $source['id'],
            'source_country' => $source['country_code'],
            'local_testimonial_count' => $local,
            'effective_testimonial_count' => $effective,
            'version' => $landing['version'],
        ];
    }
}
