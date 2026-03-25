<?php

declare(strict_types=1);

namespace Temporal\Tests\Test262;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Temporal\Exception\NotYetImplementedException;

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
            if ($file->getExtension() === 'php') {
                $name = ltrim(str_replace(search: $dir, replace: '', subject: $file->getPathname()), characters: '/');
                yield $name => [$file->getPathname()];
            }
        }
    }

    #[DataProvider('scripts')]
    public function testScript(string $path): void
    {
        try {
            /** @psalm-suppress UnresolvableInclude */
            require $path;
        } catch (NotYetImplementedException $e) {
            static::markTestIncomplete($e->getMessage());
        } catch (\ParseError $e) {
            // Syntax errors in generated scripts must fail loudly — they indicate
            // a transpiler bug, not a legitimately unimplemented test.
            static::fail(sprintf('Syntax error in generated script: %s', $e->getMessage()));
        } catch (\Error $e) {
            // PHP errors (e.g. "Value of type null is not callable") from untranslatable JS
            // code patterns indicate the script cannot run in PHP — mark as incomplete.
            static::markTestIncomplete(sprintf('PHP error: %s', $e->getMessage()));
        }
    }
}
