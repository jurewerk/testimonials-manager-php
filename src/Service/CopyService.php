<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\AiProvider;
use App\Repository\ActivityRepository;
use App\Repository\ImageRepository;
use App\Repository\LandingRepository;
use App\Repository\TestimonialRepository;
use App\Support\Database;
use App\Support\HttpException;
use App\Support\ValidationException;

/**
 * Copies testimonials between countries of the same product, optionally running
 * each row through a mock AI provider first (translation and author names).
 *
 * Copies are independent records with their own image files, so editing or
 * deleting a copy never touches the source.
 */
class CopyService
{
    public const STRATEGIES = ['replace', 'append', 'empty', 'skip'];

    private Database $db;

    private LandingRepository $landings;

    private TestimonialRepository $testimonials;

    private ImageRepository $images;

    private ImageService $imageService;

    private ActivityRepository $activity;

    public function __construct(
        Database $db,
        LandingRepository $landings,
        TestimonialRepository $testimonials,
        ImageRepository $images,
        ImageService $imageService,
        ActivityRepository $activity
    ) {
        $this->db = $db;
        $this->landings = $landings;
        $this->testimonials = $testimonials;
        $this->images = $images;
        $this->imageService = $imageService;
        $this->activity = $activity;
    }

    /**
     * @param  array|null  $only  optional subset of source testimonial ids
     */
    public function copy(array $destination, array $source, string $strategy, int $version, ?int $userId, ?AiProvider $provider = null, bool $generateNames = false, ?array $only = null): array
    {
        if (! in_array($strategy, self::STRATEGIES, true)) {
            throw new ValidationException(['strategy' => ['Unknown copy strategy.']]);
        }

        if ($source['id'] === $destination['id']) {
            throw new ValidationException(['source_landing_id' => ['Choose a different country to copy from.']]);
        }

        if ($source['product_id'] !== $destination['product_id']) {
            throw new ValidationException(['source_landing_id' => ['Both landings must belong to the same product.']]);
        }

        return $this->db->transaction(function () use ($destination, $source, $strategy, $version, $userId, $provider, $generateNames, $only) {
            $current = $this->landings->lockForUpdate($destination['id']);

            if ($current === null) {
                throw new HttpException('Landing not found.', 404);
            }

            if ((int) $current['version'] !== $version) {
                throw new HttpException('This landing changed in another session. Reload and try again.', 409);
            }

            $existing = $this->testimonials->allForLanding($destination['id']);

            if ($strategy === 'empty' && $existing !== []) {
                return ['copied' => 0, 'skipped' => 0, 'removed' => 0, 'version' => (int) $current['version']];
            }

            // Source rows include inactive ones: the editor is copying content,
            // not deciding what is published.
            $rows = $this->testimonials->allForLanding($source['id']);

            if ($only !== null) {
                $wanted = array_flip(array_map('intval', $only));
                $rows = array_values(array_filter($rows, static fn ($r) => isset($wanted[(int) $r['id']])));
            }

            $removed = 0;

            if ($strategy === 'replace') {
                foreach ($existing as $row) {
                    $this->deleteWithImages((int) $row['id']);
                    $removed++;
                }

                $existing = [];
            }

            // For "skip", build a set of author+comment hashes already present.
            $seen = [];

            foreach ($existing as $row) {
                $seen[hash('sha256', $row['author_name']."\0".$row['comment'])] = true;
            }

            $order = $this->testimonials->nextSortOrder($destination['id']);
            $copied = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $authorName = $row['author_name'];
                $comment = $row['comment'];

                if ($provider !== null) {
                    $comment = $provider->translate($comment, $destination['country_code']);

                    if ($generateNames) {
                        $authorName = $provider->generateAuthorName($destination['country_code'], $row['gender']);
                    }
                }

                if ($strategy === 'skip' && isset($seen[hash('sha256', $authorName."\0".$comment)])) {
                    $skipped++;

                    continue;
                }

                if (mb_strlen($comment) > 2000 || mb_strlen($authorName) > 120) {
                    throw new ValidationException(['comment' => ['A translated testimonial exceeded the allowed length.']]);
                }

                $newId = $this->testimonials->create([
                    'landing_id' => $destination['id'],
                    'author_name' => $authorName,
                    'comment' => $comment,
                    'link' => $row['link'],
                    'rating_mode' => $row['rating_mode'],
                    'rating' => $row['rating'],
                    'gender' => $row['gender'],
                    'is_active' => (bool) $row['is_active'],
                    'sort_order' => $order++,
                    'created_by' => $userId,
                ]);

                $this->copyImages((int) $row['id'], $newId);
                $this->activity->record($userId, 'copied', 'testimonial', $newId, null, ['landing_id' => $destination['id'], 'author_name' => $authorName]);
                $seen[hash('sha256', $authorName."\0".$comment)] = true;
                $copied++;
            }

            return [
                'copied' => $copied,
                'skipped' => $skipped,
                'removed' => $removed,
                'version' => $this->landings->bumpVersion($destination['id']),
            ];
        });
    }

    /** Independent files, so the copy can be edited and deleted on its own. */
    private function copyImages(int $sourceId, int $targetId): void
    {
        $order = 0;

        foreach ($this->images->forTestimonial($sourceId) as $image) {
            $this->images->create([
                'testimonial_id' => $targetId,
                'path' => $this->imageService->copyFile($image['path']),
                'thumbnail_path' => $this->imageService->copyFile($image['thumbnail_path']),
                'original_filename' => $image['original_filename'],
                'mime_type' => $image['mime_type'],
                'size' => (int) $image['size'],
                'width' => (int) $image['width'],
                'height' => (int) $image['height'],
                'sort_order' => $order++,
            ]);
        }
    }

    private function deleteWithImages(int $testimonialId): void
    {
        foreach ($this->images->forTestimonial($testimonialId) as $image) {
            $this->imageService->deleteFile($image['path']);
            $this->imageService->deleteFile($image['thumbnail_path']);
        }

        $this->testimonials->delete($testimonialId);
    }
}
