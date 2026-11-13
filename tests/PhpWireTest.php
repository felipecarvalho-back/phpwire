<?php

declare(strict_types=1);

namespace Tests;

use App\Components\Counter;
use PhpWire\Attributes\Locked;
use PhpWire\Attributes\Validate;
use PhpWire\Component;
use PhpWire\PhpWire;
use PHPUnit\Framework\TestCase;
use RuntimeException;

enum StatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
}

final class ProfileComponent extends Component
{
    #[Locked]
    public int $userId = 42;

    #[Validate('required|min:3')]
    public string $name = 'Felipe';

    public StatusEnum $status = StatusEnum::Draft;

    public string $hookLog = '';

    public function updatingName(string $value): void
    {
        $this->hookLog = 'updating:' . $value;
    }

    public function updatedName(string $value): void
    {
        $this->hookLog .= '|updated:' . $value;
    }

    public function save(): void
    {
        $this->validate();
    }

    public function render(): string
    {
        return $this->view('profile');
    }
}

final class PhpWireTest extends TestCase
{
    private string $tempViewDir;
    private string $tempCacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpwire_test_cache_' . uniqid();
        $this->tempViewDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpwire_test_views_' . uniqid();
        mkdir($this->tempViewDir, 0777, true);

        file_put_contents(
            $this->tempViewDir . DIRECTORY_SEPARATOR . 'profile.wire.php',
            <<<'BLADE'
<div>
    <span class="user">{{ $userId }}</span>
    <span class="name">{{ $name }}</span>
    <span class="status">{{ $status }}</span>
    @if (!empty($errors['name']))
        <p class="error">{{ $errors['name'] }}</p>
    @endif
</div>
BLADE
        );

        PhpWire::init([
            'views' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views',
            'cache_path' => $this->tempCacheDir,
            'secret' => 'test-secret-key-123',
            'auto_listen' => false,
            'components' => [
                'counter' => Counter::class,
                'profile' => ProfileComponent::class,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempViewDir);
        $this->removeDirectory($this->tempCacheDir);
        parent::tearDown();
    }

    public function testMountGeneratesSignedSnapshotAndBladeOneOutput(): void
    {
        $html = PhpWire::mount('counter');

        $this->assertStringContainsString('wire:snapshot="', $html);
        $this->assertStringContainsString('<div class="number">0</div>', $html);
        $this->assertStringNotContainsString('Zerar', $html);

        $snapshot = $this->extractSnapshot($html);
        $this->assertSame(Counter::class, $snapshot['class']);
        $this->assertSame(['count' => 0, 'step' => 1], $snapshot['state']);
        $this->assertNotEmpty($snapshot['checksum']);
    }

    public function testHandlePayloadExecutesActionsWithArgumentsAndUpdates(): void
    {
        $initialHtml = PhpWire::mount('counter');
        $snapshot = $this->extractSnapshot($initialHtml);

        $res1 = PhpWire::handlePayload([
            'snapshot' => $snapshot,
            'updates' => ['step' => '3'],
            'action' => 'increment',
        ]);

        $this->assertSame(3, $res1['snapshot']['state']['count']);
        $this->assertSame(3, $res1['snapshot']['state']['step']);
        $this->assertStringContainsString('Zerar', $res1['html']);

        $res2 = PhpWire::handlePayload([
            'snapshot' => $res1['snapshot'],
            'action' => 'increment(5)',
        ]);

        $this->assertSame(8, $res2['snapshot']['state']['count']);

        $res3 = PhpWire::handlePayload([
            'snapshot' => $res2['snapshot'],
            'action' => 'decrement(2)',
        ]);

        $this->assertSame(6, $res3['snapshot']['state']['count']);
    }

    public function testTamperedSnapshotIsRejected(): void
    {
        $initialHtml = PhpWire::mount('counter');
        $snapshot = $this->extractSnapshot($initialHtml);
        $snapshot['state']['count'] = 9999;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('adulterado');

        PhpWire::handlePayload([
            'snapshot' => $snapshot,
            'action' => 'increment',
        ]);
    }

    public function testLockedPropertyCannotBeUpdatedByClient(): void
    {
        PhpWire::setViewPath($this->tempViewDir);
        $html = PhpWire::mount('profile');
        $snapshot = $this->extractSnapshot($html);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('#[Locked]');

        PhpWire::handlePayload([
            'snapshot' => $snapshot,
            'updates' => ['userId' => 999],
        ]);
    }

    public function testInternalComponentMethodsCannotBeInvokedAsActions(): void
    {
        $initialHtml = PhpWire::mount('counter');
        $snapshot = $this->extractSnapshot($initialHtml);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Método interno');

        PhpWire::handlePayload([
            'snapshot' => $snapshot,
            'action' => 'render',
        ]);
    }

    public function testValidationErrorsAreRenderedWithoutCrashing(): void
    {
        PhpWire::setViewPath($this->tempViewDir);
        $html = PhpWire::mount('profile');
        $snapshot = $this->extractSnapshot($html);

        $res = PhpWire::handlePayload([
            'snapshot' => $snapshot,
            'updates' => ['name' => 'A'],
            'action' => 'save',
        ]);

        $this->assertStringContainsString('class="error"', $res['html']);
        $this->assertStringContainsString('pelo menos 3 caracteres', $res['html']);
        $this->assertSame('updating:A|updated:A', $res['snapshot']['state']['hookLog']);
    }

    public function testBackedEnumIsHydratedFromSnapshot(): void
    {
        PhpWire::setViewPath($this->tempViewDir);
        $html = PhpWire::mount('profile');
        $snapshot = $this->extractSnapshot($html);

        $res = PhpWire::handlePayload([
            'snapshot' => $snapshot,
            'updates' => ['status' => 'published'],
        ]);

        $this->assertSame('published', $res['snapshot']['state']['status']);
        $this->assertStringContainsString('<span class="status">published</span>', $res['html']);
    }

    public function testScriptsContainIdiomorphAndDirectives(): void
    {
        $scripts = PhpWire::scripts();

        $this->assertStringContainsString('idiomorph', $scripts);
        $this->assertStringContainsString('wire:model', $scripts);
        $this->assertStringContainsString('wire:submit', $scripts);
        $this->assertStringContainsString('wire\\:loading', $scripts);
    }

    /**
     * @return array{class: string, state: array<string, mixed>, checksum: string}
     */
    private function extractSnapshot(string $html): array
    {
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? '');

        $decoded = json_decode(htmlspecialchars_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
