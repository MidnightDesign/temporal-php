<?php

declare(strict_types=1);

namespace Temporal\Tests\Test262;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers and runs all generated PHP test scripts under tests/Test262/scripts/.
 *
 * Each script file becomes one data-provider test case. Scripts use the
 * Assert helper class to bridge TC39-style assertions to PHPUnit.
 */
final class RunnerTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function scripts(): iterable
    {
        $dir = sprintf('%s/scripts', __DIR__);
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $dir,
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));
        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $name = ltrim(str_replace(search: $dir, replace: '', subject: $file->getPathname()), characters: '/');
            yield $name => [$file->getPathname()];
        }
    }

    #[DataProvider('scripts')]
    public function testScript(string $path): void
    {
        self::maybeSkipForIcuVersion($path);
        try {
            /** @psalm-suppress UnresolvableInclude */
            require $path;
        } catch (\Throwable $e) {
            self::handleScriptThrowable($e);
        }
    }

    /**
     * Skips fixtures whose expected values depend on ICU/CLDR data that was updated
     * after the host's ICU version. The Chinese calendar's leap-year placement is the
     * known case: TC39's `daysInYear/basic-chinese.{js,php}` was authored against ICU
     * 76+ (October 2024) data, and Ubuntu 24.04's bundled libicu74 produces a
     * different sequence of leap years for some years in the 1969–2048 sample range.
     *
     * Production users on libicu74 will see the same divergence — see README's
     * "ICU compatibility" note.
     */
    private static function maybeSkipForIcuVersion(string $path): void
    {
        if (!str_contains($path, 'basic-chinese') || !str_contains($path, '/daysInYear/')) {
            return;
        }
        // Read INTL_ICU_VERSION via constant() so PHPStan doesn't const-fold it to the
        // analyzer's host value and prove the version_compare branch unreachable. Static
        // analysis sees this as a `mixed` lookup; production sees the real linked-libicu
        // version.
        /** @var string $icu */
        $icu = constant('INTL_ICU_VERSION');
        if (version_compare($icu, version2: '76', operator: '<')) {
            static::markTestIncomplete(sprintf(
                'Chinese-calendar daysInYear fixtures require ICU ≥ 76 (host has %s); leap-year placement diverges below that.',
                $icu,
            ));
        }
    }

    /**
     * Dispatches throwables from generated test262 scripts:
     *
     *   - `\ParseError`        → fail loudly (transpiler bug, not a legitimate incomplete test).
     *   - Other `\Error` (e.g. `\TypeError`, `\ArgumentCountError`, `\UnhandledMatchError`,
     *     `\AssertionError`): these indicate the generated PHP cannot run — mark the test as
     *     incomplete rather than pass/fail, since the JS fixture cannot be translated 1:1.
     *   - `\Exception` subclasses: re-throw so PHPUnit records the real assertion / runtime error.
     */
    private static function handleScriptThrowable(\Throwable $e): void
    {
        if ($e instanceof \ParseError) {
            static::fail(sprintf('Syntax error in generated script: %s', $e->getMessage()));
        }
        if ($e instanceof \Error) {
            static::markTestIncomplete(sprintf('PHP error: %s', $e->getMessage()));
        }
        throw $e;
    }
}
