<?php

/**
 * Generates the local sample content tree used by the docker environment.
 *
 *   docker compose exec web php dev/sample-files.php
 *
 * The tree it writes (files/) is gitignored — it stands in for real client
 * content, which has no business being in the repository. Re-running is safe:
 * existing files are overwritten, nothing is deleted. To start clean, remove
 * the files/ directory yourself first.
 *
 * The medias deliberately cover every entry-point case the indexer has to
 * resolve, so that `bin/scan.php` can be exercised against all of them:
 *
 *   index      an index.html at the media root          -> entry point
 *   single     one non-index .html at the root          -> entry point
 *   ambiguous  several .html, no index.html             -> unusable, no link
 *   opaque     no html at all                           -> unusable, no link
 */

declare(strict_types=1);

use MediasIndex\Support\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

// Written wherever the active configuration says the content root is, so this
// stays correct if the docker layout or the config ever moves.
$root = Config::load()->path('paths.files');

/**
 * client => project => media => kind
 */
$tree = [
    'atelier-nord' => [
        'expo-lumieres' => [
            'salle-bleue' => 'index',
            'salle-rouge' => 'single',
        ],
        'catalogue-2026' => [
            'piece-001' => 'index',
            'brouillons' => 'opaque',
        ],
    ],
    'musee-sud' => [
        'collection-permanente' => [
            'vitrine-a' => 'index',
            'vitrine-b' => 'ambiguous',
        ],
        'archives' => [
            'fonds-ancien' => 'opaque',
            'visite-360' => 'krpano',
        ],
    ],
];

$written = 0;

$write = static function (string $path, string $contents) use (&$written): void {
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0o775, true);
    }

    file_put_contents($path, $contents);
    $written++;
};

/** A flat colour with its name burnt in, so thumbnails are visually distinct. */
$image = static function (string $path, int $width, int $height, array $rgb) use (&$written): void {
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0o775, true);
    }

    $im = imagecreatetruecolor($width, $height);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
    imagestring($im, 5, 12, 12, basename(dirname($path)), imagecolorallocate($im, 255, 255, 255));
    imagejpeg($im, $path, 85);
    imagedestroy($im);
    $written++;
};

$page = static fn (string $title, string $body): string => <<<HTML
    <!doctype html>
    <html lang="fr">
    <meta charset="utf-8">
    <title>{$title}</title>
    <h1>{$title}</h1>
    {$body}
    HTML;

foreach ($tree as $client => $projects) {
    foreach ($projects as $project => $medias) {
        foreach ($medias as $media => $kind) {
            $dir = "{$root}/{$client}/{$project}/{$media}";
            $label = "{$client} / {$project} / {$media}";

            switch ($kind) {
                case 'index':
                    $write($dir . '/index.html', $page(
                        ucfirst(str_replace('-', ' ', $media)),
                        "<p>Contenu de démonstration pour {$label}.</p>\n"
                        . "<p>Cette media a un index.html : elle est liable et embarquable.</p>\n"
                        . '<img src="assets/cover.jpg" alt="">',
                    ));
                    $image($dir . '/assets/cover.jpg', 1200, 800, [38, 84, 152]);
                    $write($dir . '/assets/style.css', "body { font-family: sans-serif; margin: 2rem; }\n");
                    break;

                case 'single':
                    $write($dir . '/presentation.html', $page(
                        ucfirst(str_replace('-', ' ', $media)),
                        "<p>Contenu de démonstration pour {$label}.</p>\n"
                        . '<p>Pas d\'index.html, mais un seul fichier html à la racine : '
                        . "c'est lui le point d'entrée.</p>",
                    ));
                    $image($dir . '/preview.jpg', 900, 600, [176, 64, 48]);
                    break;

                case 'ambiguous':
                    $write($dir . '/avant.html', $page('Avant', "<p>{$label} — état avant restauration.</p>"));
                    $write($dir . '/apres.html', $page('Après', "<p>{$label} — état après restauration.</p>"));
                    $image($dir . '/photo.jpg', 640, 480, [180, 140, 40]);
                    break;

                case 'krpano':
                    // A minimal stand-in for a krpano export: enough for
                    // KrpanoProbe to recognise, title and preview, without the
                    // tens of megabytes of tiles a real tour carries.
                    $write($dir . '/tour.xml', <<<XML
                        <krpano version="1.24" title="Visite 360 — {$media}">
                            <scene name="scene_1" title="Entrée" thumburl="panos/scene_1.tiles/thumb.jpg">
                                <preview url="panos/scene_1.tiles/preview.jpg" />
                            </scene>
                            <scene name="scene_2" title="Salle" thumburl="panos/scene_2.tiles/thumb.jpg" />
                        </krpano>
                        XML);
                    $write($dir . '/tour.html', $page(
                        'Visite 360',
                        '<p>Chargeur krpano de démonstration.</p>',
                    ));
                    $write($dir . '/tour.js', "// krpano loader stub\n");
                    $image($dir . '/panos/scene_1.tiles/thumb.jpg', 240, 160, [40, 110, 90]);
                    $image($dir . '/panos/scene_1.tiles/preview.jpg', 1024, 512, [40, 110, 90]);
                    $image($dir . '/panos/scene_2.tiles/thumb.jpg', 240, 160, [110, 70, 40]);
                    break;

                case 'opaque':
                    $write($dir . '/notes.txt', "Notes internes pour {$label}.\nAucun contenu web ici.\n");
                    $write($dir . '/inventaire.csv', "reference;designation;quantite\nA-001;Socle;2\nA-002;Cimaise;5\n");
                    $write($dir . '/dump.json', json_encode(
                        ['source' => $label, 'items' => 12, 'complete' => false],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                    ) . "\n");
                    break;
            }
        }
    }
}

printf("%d files written under %s%s", $written, $root, PHP_EOL);
