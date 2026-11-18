<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use ItsJustVita\WpMigrator\Detection\Finding;
use ItsJustVita\WpMigrator\Detection\Suspicious\SuspiciousContent;

/**
 * @return array<string, string>
 */
function suspicions(): array
{
    $findings = app(SuspiciousContent::class)->findings();

    return array_combine(
        array_map(fn (Finding $finding): string => $finding->key, $findings),
        array_map(fn (Finding $finding): string => $finding->label.': '.$finding->summary(), $findings),
    );
}

beforeEach(function () {
    $this->site = $this->source();
    $this->site->option('home', 'https://old.example.com');
});

it('finds nothing on a clean site', function () {
    $this->site->post(['post_content' => '<p>Hello <a href="https://old.example.com/a">a</a></p><iframe src="https://www.youtube.com/embed/x"></iframe>']);
    $this->site->option('blogname', 'Acme');

    expect(suspicions())->toBe([]);
});

it('finds injected code in the posts that are imported', function () {
    $first = $this->site->post(['post_content' => '<p>Hi</p><script src="https://evil.example/x.js"></script>']);
    $second = $this->site->page(['post_content' => '<img src=x onerror="alert(1)">', 'post_excerpt' => '<script>eval(atob("x"))</script>']);
    $this->site->post(['post_content' => '<script>trashed()</script>', 'post_status' => 'trash']);
    $this->site->post(['post_content' => '<script>revision()</script>', 'post_type' => 'revision']);
    $this->site->post(['post_content' => '<script>form()</script>', 'post_type' => 'wpcf7_contact_form']);
    $hidden = $this->site->post(['post_content' => '<div style="display:none"><a href="https://pills.example/buy">cheap</a></div><iframe src="https://casino.example/"></iframe><a href="javascript:void(0)">x</a>']);

    expect(suspicions())->toBe([
        'scripts' => "Scripts in the content: 2 posts (IDs {$first}, {$second}); from evil.example, inline",
        'event-handlers' => "Event handlers in the content: 1 post (ID {$second}); onerror",
        'script-links' => "Links that run script: 1 post (ID {$hidden}); javascript:",
        'frames' => "Frames from other sites: 1 post (ID {$hidden}); from casino.example",
        'hidden-links' => "Hidden links: 1 post (ID {$hidden}); to pills.example",
        'obfuscation' => "Obfuscated code: 1 post (ID {$second}); eval, atob",
    ]);
});

it('looks into the Elementor data of a post', function () {
    $page = $this->site->page(['post_content' => '<p>Clean copy</p>']);
    $this->site->postMeta($page, '_elementor_data', json_encode([
        ['elType' => 'widget', 'widgetType' => 'html', 'settings' => ['html' => '<script src="https://evil.example/e.js"></script>']],
    ]));
    $broken = $this->site->page(['post_content' => '']);
    $this->site->postMeta($broken, '_elementor_data', '{broken <iframe src="https://casino.example/">');

    expect(suspicions())->toBe([
        'scripts' => "Scripts in the content: 1 post (ID {$page}); from evil.example",
        'frames' => "Frames from other sites: 1 post (ID {$broken}); from casino.example",
    ]);
});

it('counts many posts but names only a few', function () {
    $ids = [];

    foreach (range(1, 7) as $i) {
        $ids[] = $this->site->post(['post_content' => '<script src="https://h'.$i.'.example/x.js"></script>']);
    }

    expect(suspicions()['scripts'])->toBe(sprintf(
        'Scripts in the content: 7 posts (IDs %s, …); from h1.example, h2.example, h3.example, h4.example, h5.example',
        implode(', ', array_slice($ids, 0, 5)),
    ));
});

it('finds code in options, but not in transients', function () {
    $this->site->option('widget_custom_html', serialize([2 => ['content' => '<script>track()</script>']]));
    $this->site->option('sso_secret', 'eval(base64_decode("x"))');
    $this->site->option('_transient_feed', '<script>cached()</script>');
    $this->site->option('_site_transient_x', 'eval(1)');
    $this->site->option('blogdescription', 'Just another site');

    expect(suspicions())->toBe(['options' => 'Code in options: 2 options: sso_secret, widget_custom_html']);
});

it('finds program files in the uploads, but not media or silent index files', function () {
    $uploads = sys_get_temp_dir().'/wp-migrator-uploads-'.uniqid();
    File::ensureDirectoryExists($uploads.'/2026/01');
    File::ensureDirectoryExists($uploads.'/cache/deep');
    file_put_contents($uploads.'/2026/01/photo.jpg', 'jpg');
    file_put_contents($uploads.'/2026/01/shell.php', '<?php echo 1;');
    file_put_contents($uploads.'/cache/deep/x.php.jpg', '<?php');
    file_put_contents($uploads.'/index.php', "<?php\n// Silence is golden.\n");
    file_put_contents($uploads.'/.htaccess', 'Deny from all');
    config()->set('wp-migrator.source.uploads_path', $uploads);

    expect(suspicions())->toBe(['uploads' => 'Program files in the uploads: 2 files: 2026/01/shell.php, cache/deep/x.php.jpg']);

    File::deleteDirectory($uploads);
});

it('does not take program files for silent index files', function () {
    $uploads = sys_get_temp_dir().'/wp-migrator-uploads-'.uniqid();
    File::ensureDirectoryExists($uploads.'/a');
    file_put_contents($uploads.'/a/index.php', '<?php echo 1;');
    file_put_contents($uploads.'/b.php', "<?php\n// Silence is golden.\n");
    foreach (range(1, 4) as $i) {
        file_put_contents($uploads.'/c'.$i.'.php', '<?php');
    }
    config()->set('wp-migrator.source.uploads_path', $uploads);

    expect(suspicions())->toBe(['uploads' => 'Program files in the uploads: 6 files: a/index.php, b.php, c1.php, c2.php, c3.php, …']);

    File::deleteDirectory($uploads);
});

it('skips the uploads when there are none to read', function () {
    config()->set('wp-migrator.source.uploads_path', '/nonexistent/uploads');

    expect(suspicions())->toBe([]);

    config()->set('wp-migrator.source.uploads_path', null);

    expect(suspicions())->toBe([]);
});

it('explains every kind of finding', function () {
    foreach (['scripts', 'event-handlers', 'script-links', 'frames', 'hidden-links', 'obfuscation'] as $key) {
        expect(SuspiciousContent::advice($key))->toContain('The import keeps');
    }

    expect(SuspiciousContent::advice('options'))->toContain('Options are not imported')
        ->and(SuspiciousContent::advice('uploads'))->toContain('never copies');
});
