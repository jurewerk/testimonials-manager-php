<?php

declare(strict_types=1);

/**
 * Creates the demo content: an administrator, three offline demo products with
 * localized landings, testimonials covering every rating mode and gender, and
 * generated image fixtures.
 *
 * Usage: php bin/seed.php
 */

require dirname(__DIR__).'/src/autoload.php';

use App\App;

$config = require dirname(__DIR__).'/config/config.php';
$app = new App($config);
$db = $app->db();

$db->execute('SET FOREIGN_KEY_CHECKS = 0');
foreach (['activity_logs', 'testimonial_images', 'testimonials', 'landings', 'products', 'users'] as $table) {
    $db->execute("TRUNCATE TABLE `$table`");
}
$db->execute('SET FOREIGN_KEY_CHECKS = 1');

// Remove any image files left from a previous seed.
foreach (glob($config['uploads']['path'].'/*') ?: [] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

$adminId = $app->users()->create('Demo Administrator', 'admin@example.test', 'local-demo-only');
echo "Administrator: admin@example.test / local-demo-only\n";

$products = [
    'AURORA-01' => ['Aurora ambient lamp', 'A softer light for everyday spaces. Rechargeable, portable, and made for slow evenings.'],
    'TRAIL-02' => ['Trail everyday backpack', 'Room for every adventure. Lightweight construction with thoughtful everyday organization.'],
    'BLOOM-03' => ['Bloom self-watering planter', 'A little more green, a little less effort. Keep your favorite plants thriving.'],
];

$countries = ['EN', 'SI', 'IT', 'DE', 'FR'];

$testimonialFixtures = [
    ['Emma Wilson', 'Beautifully made and even better in person. It has become part of my daily routine.', 'female', true, 'fixed', 5],
    ['Alex Morgan', 'Exactly what I was looking for. Thoughtful details and excellent quality.', 'unisex', true, 'random', null],
    ['James Parker', 'Arrived quickly and works well. A lovely addition to our home.', 'male', false, 'fixed', 4],
];

// Content with Cyrillic, Greek and diacritics proves the utf8mb4 collation.
$localised = [
    'SI' => 'Odličen izdelek, uporabljam ga vsak dan. Šumniki: čšž.',
    'IT' => 'Un prodotto bellissimo, lo uso ogni giorno.',
    'DE' => 'Sehr schöne Qualität — größer als erwartet.',
    'FR' => 'Très beau produit, je l’utilise tous les jours.',
];

$imageService = $app->imageService();

foreach ($products as $sku => [$title, $description]) {
    $productId = $app->products()->findOrCreateBySku($sku);

    foreach ($countries as $country) {
        $landingId = $db->insert(
            'INSERT INTO landings (external_id, product_id, country_code, is_master, title, description, landing_url, product_image_url, last_synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NOW())',
            [
                'demo-'.$sku.'-'.$country, $productId, $country, $country === 'EN' ? 1 : 0,
                $title, $description, 'https://example.com/'.strtolower($country).'/'.$sku,
            ]
        );

        // Only EN and IT get their own rows, so the other countries demonstrate
        // the English fallback in the interface.
        if (! in_array($country, ['EN', 'IT'], true)) {
            continue;
        }

        foreach ($testimonialFixtures as $index => [$author, $comment, $gender, $active, $mode, $rating]) {
            $testimonialId = $app->testimonials()->create([
                'landing_id' => $landingId,
                'author_name' => $author,
                'comment' => $country === 'EN' ? $comment : ($localised[$country] ?? $comment),
                'link' => $index === 0 ? 'https://example.com/'.strtolower($country).'/'.$sku : null,
                'rating_mode' => $mode,
                'rating' => $rating,
                'gender' => $gender,
                'is_active' => $active,
                'sort_order' => $index,
                'created_by' => $adminId,
            ]);

            $app->activity()->record($adminId, 'created', 'testimonial', $testimonialId, null, ['author_name' => $author]);

            if ($index !== 0) {
                continue;
            }

            // Two generated fixtures per first testimonial.
            $uploads = [];

            for ($i = 0; $i < 2; $i++) {
                $image = imagecreatetruecolor(640, 480);
                imagefill($image, 0, 0, imagecolorallocate($image, 210 + $i * 10, 225, 215));
                $ink = imagecolorallocate($image, 45, 85, 70);
                imagefilledellipse($image, 320, 240, 180 + $i * 30, 230, $ink);
                imagestring($image, 5, 210, 400, 'DEMO PRODUCT PHOTO', $ink);

                $tmp = tempnam(sys_get_temp_dir(), 'seed');
                imagepng($image, $tmp);
                imagedestroy($image);

                $uploads[] = ['name' => 'demo-'.$i.'.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
            }

            try {
                $imageService->store($testimonialId, $uploads, 0);
            } finally {
                foreach ($uploads as $upload) {
                    @unlink($upload['tmp_name']);
                }
            }
        }
    }
}

$counts = $db->selectOne('SELECT
    (SELECT COUNT(*) FROM products) p,
    (SELECT COUNT(*) FROM landings) l,
    (SELECT COUNT(*) FROM testimonials) t,
    (SELECT COUNT(*) FROM testimonial_images) i');

printf("Seeded %d products, %d landings, %d testimonials, %d images.\n", $counts['p'], $counts['l'], $counts['t'], $counts['i']);
