<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LandingRepository;
use App\Repository\TestimonialRepository;
use App\Support\Database;
use App\Support\HttpException;
use App\Support\ValidationException;

class OrderingService
{
    private Database $db;

    private LandingRepository $landings;

    private TestimonialRepository $testimonials;

    public function __construct(Database $db, LandingRepository $landings, TestimonialRepository $testimonials)
    {
        $this->db = $db;
        $this->landings = $landings;
        $this->testimonials = $testimonials;
    }

    /**
     * Persists one complete ordering for a landing.
     *
     * The submitted list must be exactly the landing's current set: ordering a
     * paginated view must still describe the whole collection, and a stale
     * version means someone else reordered first, which is a 409.
     */
    public function reorder(array $landing, array $ids, int $version): int
    {
        return $this->db->transaction(function () use ($landing, $ids, $version) {
            $current = $this->landings->lockForUpdate($landing['id']);

            if ($current === null) {
                throw new HttpException('Landing not found.', 404);
            }

            if ((int) $current['version'] !== $version) {
                throw new HttpException('This landing changed in another session. Reload and try again.', 409);
            }

            $existing = $this->testimonials->idsForLanding($landing['id']);
            $ids = array_map('intval', $ids);

            if (count($ids) !== count(array_unique($ids))) {
                throw new ValidationException(['ids' => ['The order contains duplicate entries.']]);
            }

            sort($existing);
            $sorted = $ids;
            sort($sorted);

            if ($existing !== $sorted) {
                throw new ValidationException(['ids' => ['The order must list every testimonial of this landing exactly once.']]);
            }

            $this->testimonials->applyOrder($ids);

            return $this->landings->bumpVersion($landing['id']);
        });
    }
}
