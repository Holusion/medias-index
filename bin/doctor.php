<?php

/**
 * Environment check. Run it locally after `docker compose up`, and once on the
 * host after the first deployment:
 *
 *   php bin/doctor.php
 *
 * Reports every problem it finds rather than stopping at the first one.
 * Exit code 0 = usable, 1 = at least one failure.
 */

declare(strict_types=1);

use MediasIndex\Support\Config;
use MediasIndex\Support\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

$failures = 0;
$warnings = 0;
$cli = PHP_SAPI === 'cli';

// Always plain text: over HTTP this is captured by DoctorController and served
// as text/plain, so escaping it for HTML would print the entities literally.
$line = static function (string $mark, string $label, string $detail): void {
    printf('[%s] %-28s %s%s', $mark, $label, $detail, PHP_EOL);
};

$report = static function (string $label, bool $ok, string $detail = '') use (&$failures, $line): void {
    if (!$ok) {
        $failures++;
    }

    $line($ok ? 'OK  ' : 'FAIL', $label, $detail);
};

/** Not fatal, but something you should look at before calling the install done. */
$warn = static function (string $label, string $detail) use (&$warnings, $line): void {
    $warnings++;
    $line('WARN', $label, $detail);
};

/**
 * The account this check is running as.
 *
 * Worth naming, because the same check answers differently from the CLI and
 * over HTTP when cron and the web server run as different users — which reads
 * as a contradiction until you can see who asked.
 */
function processUser(): string
{
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $user = posix_getpwuid(posix_geteuid());

        if (is_array($user) && isset($user['name'])) {
            return (string) $user['name'];
        }
    }

    return (string) (getenv('USER') ?: getenv('USERNAME') ?: 'the current user');
}

// --- PHP itself --------------------------------------------------------------

$report('php version', PHP_VERSION_ID >= 80200, PHP_VERSION . ' (need >= 8.2)');

foreach (['pdo', 'pdo_mysql', 'gd', 'json', 'mbstring'] as $extension) {
    $report('ext ' . $extension, extension_loaded($extension));
}

if (extension_loaded('gd')) {
    $gd = gd_info();
    $report('gd jpeg support', !empty($gd['JPEG Support']), 'GD ' . ($gd['GD Version'] ?? '?'));
}

$report(
    'limits',
    true,
    sprintf(
        'memory_limit=%s max_execution_time=%s',
        ini_get('memory_limit'),
        ini_get('max_execution_time'),
    ),
);

// --- Configuration and paths -------------------------------------------------

$config = null;

try {
    $config = Config::load();
    $report('config', true, (string) $config->sourcePath());
} catch (Throwable $e) {
    $report('config', false, $e->getMessage());
}

// Where the configuration sits decides whether a deployment can delete it and
// whether a URL can reach it. Both are worth flagging loudly.
if ($config !== null && ($source = $config->sourcePath()) !== null) {
    $app = Config::rootDir();
    $www = dirname($app);

    if ($source === (getenv('MEDIAS_INDEX_CONFIG') ?: null)) {
        // Pointed at deliberately (docker, or a SetEnv in .htaccess), so where
        // it sits is a decision rather than an accident.
        $report('config location', true, 'explicit MEDIAS_INDEX_CONFIG override');
    } elseif (str_starts_with($source, $app . '/')) {
        $warn(
            'config location',
            'inside the repository — a mirroring deploy will delete it; move it to ' . dirname($www) . '/config/',
        );
    } elseif (str_starts_with($source, $www . '/')) {
        $warn(
            'config location',
            'inside the document root — keep the "Require all denied" rule for it in ' . $www . '/.htaccess',
        );
    } else {
        $report('config location', true, 'outside the document root');
    }

    // Option 2 of the search path depends on the host allowing reads above the
    // document root. open_basedir is set by the host and cannot be overridden.
    $basedir = ini_get('open_basedir');
    $outside = dirname($www);
    $report(
        'reads above document root',
        is_dir($outside) && is_readable($outside),
        $basedir ? 'open_basedir=' . $basedir : 'open_basedir not set',
    );
}

if ($config !== null) {
    $files = $config->path('paths.files');
    $report('files directory', is_dir($files) && is_readable($files), $files);

    $thumbs = $config->path('paths.thumbs');

    if (!is_dir($thumbs)) {
        @mkdir($thumbs, 0o775, true);
    }

    $report('thumbs directory', is_dir($thumbs) && is_readable($thumbs), $thumbs);

    // Only whatever runs bin/scan.php writes here — Apache serves the files
    // without PHP touching them. So a web request finding the directory
    // read-only is normal wherever scans run under a different account, and a
    // warning rather than a failure. It stops being cosmetic once POST
    // /hook/scan exists, since that scans from the web process.
    if (is_dir($thumbs)) {
        is_writable($thumbs)
            ? $report('thumbs writable', true, 'by ' . processUser())
            : $warn(
                'thumbs writable',
                sprintf(
                    'not by %s — only the account running bin/scan.php needs this,'
                    . ' but the POST hook will scan as this one',
                    processUser(),
                ),
            );
    }

    // The copied links and embed snippets are used away from this site, so a
    // missing origin makes them relative and useless the moment they are pasted.
    $origin = $config->string('urls.origin', '');
    $originIsSet = $origin !== '' && !str_contains($origin, 'example.com');

    if ($originIsSet) {
        $report('public origin', true, $origin);
    } else {
        $warn('public origin', 'set urls.origin, or embed snippets will not work off-site');
    }

    $tokenIsSet = $config->string('hook.token') !== 'CHANGE_ME';
    $report(
        'hook token',
        $tokenIsSet,
        $tokenIsSet ? '' : 'still CHANGE_ME — set hook.token in ' . (string) $config->sourcePath(),
    );

    // --- Database ------------------------------------------------------------

    try {
        $database = new Database($config);
        $pdo = $database->pdo();
        $version = $pdo->query('SELECT VERSION()')?->fetchColumn();
        $report('database', true, $database->driver() . ' ' . (string) $version);
    } catch (Throwable $e) {
        $report('database', false, $e->getMessage());
    }
}

echo PHP_EOL, $failures === 0
    ? sprintf('Environment looks usable (%d warning(s)).%s', $warnings, PHP_EOL)
    : sprintf('%d check(s) failed, %d warning(s).%s', $failures, $warnings, PHP_EOL);

if ($cli) {
    exit($failures === 0 ? 0 : 1);
}
