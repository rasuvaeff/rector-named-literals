<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Tests;

use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;
use Rasuvaeff\RectorNamedLiterals\Internal\LiteralArgumentNamer;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * End-to-end: runs the real `rector process` binary over the fixture files —
 * the same path a consumer takes — and compares every produced file with its
 * committed `.expected` counterpart. `.php.fixture` naming keeps the fixtures
 * out of cs-fixer/psalm/rector of this package itself.
 */
#[Test]
#[Covers(AddNameToLiteralArgumentRector::class)]
#[Covers(LiteralArgumentNamer::class)]
final class AddNameToLiteralArgumentRectorTest
{
    #[DataProvider('suiteProvider')]
    public function appliesExpectedTransformations(string $suite): void
    {
        $fixtureDir = __DIR__ . '/fixture/' . $suite;
        $workDir = sys_get_temp_dir() . '/rector-named-literals-' . $suite . '-' . bin2hex(random_bytes(4));

        mkdir($workDir, 0o777, true);

        try {
            $fixtures = glob($fixtureDir . '/*.php.fixture') ?: [];
            Assert::true($fixtures !== [], 'fixture suite must not be empty');

            foreach ($fixtures as $fixture) {
                copy($fixture, $workDir . '/' . basename($fixture, '.fixture'));
            }

            $output = $this->runRector($workDir, __DIR__ . '/config/' . $suite . '.php');

            foreach ($fixtures as $fixture) {
                $fileName = basename($fixture, '.fixture');
                $expected = $fixtureDir . '/' . $fileName . '.expected';

                Assert::true(is_file($expected), $fileName . ' is missing its .expected counterpart');
                Assert::same(
                    (string) file_get_contents($workDir . '/' . $fileName),
                    (string) file_get_contents($expected),
                    $fileName . "\n" . $output,
                );
            }
        } finally {
            foreach (glob($workDir . '/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($workDir);
        }
    }

    public static function suiteProvider(): iterable
    {
        yield 'default (bool only)' => ['default'];
        yield 'all literals' => ['all-literals'];
    }

    public function rejectsUnknownConfigurationKey(): void
    {
        $configPath = tempnam(sys_get_temp_dir(), 'rector-cfg-') . '.php';
        file_put_contents($configPath, <<<'PHP'
            <?php

            declare(strict_types=1);

            use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;
            use Rector\Config\RectorConfig;

            return RectorConfig::configure()
                ->withConfiguredRule(AddNameToLiteralArgumentRector::class, ['nonsense' => true]);
            PHP);

        $workDir = sys_get_temp_dir() . '/rector-named-literals-invalid-' . bin2hex(random_bytes(4));
        mkdir($workDir, 0o777, true);
        file_put_contents($workDir . '/Sample.php', "<?php\nfunction f(bool \$x): void {}\nf(true);\n");

        try {
            [$exitCode, $output] = $this->runRectorRaw($workDir, $configPath);

            Assert::true($exitCode !== 0, 'invalid configuration must fail the run');
            Assert::string($output)->contains('nonsense');
        } finally {
            @unlink($workDir . '/Sample.php');
            @rmdir($workDir);
            @unlink($configPath);
        }
    }

    private function runRector(string $paths, string $config): string
    {
        [$exitCode, $output] = $this->runRectorRaw($paths, $config);

        Assert::same($exitCode, 0, 'rector process failed: ' . $output);

        return $output;
    }

    /**
     * @return array{int, string}
     */
    private function runRectorRaw(string $paths, string $config): array
    {
        $command = sprintf(
            '%s process %s --config %s --no-progress-bar --no-diffs --clear-cache 2>&1',
            escapeshellarg(dirname(__DIR__) . '/vendor/bin/rector'),
            escapeshellarg($paths),
            escapeshellarg($config),
        );

        exec($command, $lines, $exitCode);

        return [$exitCode, implode("\n", $lines)];
    }
}
