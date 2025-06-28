<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\FrenchGuidelinesChecker;

class CliTest extends TestCase
{
    private string $cliPath;
    private string $testDataDir;

    protected function setUp(): void
    {
        $this->cliPath = __DIR__ . '/../bin/check-translation';
        $this->testDataDir = __DIR__ . '/data';

        // Create test data directory if it doesn't exist
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0o755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $testFiles = glob($this->testDataDir . '/*.po');
        if ($testFiles !== false) {
            foreach ($testFiles as $file) {
                unlink($file);
            }
        }

        $bakFiles = glob($this->testDataDir . '/*.po.bak');
        if ($bakFiles !== false) {
            foreach ($bakFiles as $file) {
                unlink($file);
            }
        }
    }

    private function createTestPoFile(string $filename, string $content): string
    {
        $filePath = $this->testDataDir . '/' . $filename;
        file_put_contents($filePath, $content);
        return $filePath;
    }

    /**
     * @param array<string> $args
     * @return array{output: string, return_code: int, lines: array<string>}
     */
    private function runCli(array $args): array
    {
        $command = 'php ' . escapeshellarg($this->cliPath) . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        return [
            'output' => implode("\n", $output),
            'return_code' => $returnCode,
            'lines' => $output,
        ];
    }

    public function testHelpOutput(): void
    {
        $result = $this->runCli(['--help']);

        $this->assertEquals(0, $result['return_code']);
        $this->assertStringContainsString('Usage:', $result['output']);
        $this->assertStringContainsString('--retranslate-glossary', $result['output']);
        $this->assertStringContainsString('Retranslate only entries with glossary-review comments', $result['output']);
        $this->assertStringContainsString('--fix', $result['output']);
        $this->assertStringContainsString('--translate', $result['output']);
        $this->assertStringContainsString('--interactive', $result['output']);
    }

    public function testBasicTypographyCheck(): void
    {
        $poContent = <<<PO
            msgid "Hello!"
            msgstr "Bonjour!"
            PO;

        $testFile = $this->createTestPoFile('test-basic.po', $poContent);
        $result = $this->runCli([$testFile]);

        $this->assertEquals(1, $result['return_code']); // Should have errors
        $this->assertStringContainsString('ERROR:', $result['output']);
        $this->assertStringContainsString('Espace insécable manquant avant', $result['output']);
    }

    public function testFixFlag(): void
    {
        $poContent = <<<PO
            msgid "Hello!"
            msgstr "Bonjour!"
            PO;

        $testFile = $this->createTestPoFile('test-fix.po', $poContent);
        $result = $this->runCli(['--fix', $testFile]);

        $this->assertEquals(1, $result['return_code']); // Still has errors but content is fixed
        $this->assertStringContainsString('Fixing', $result['output']);

        // Check that backup file was created
        $this->assertFileExists($testFile . '.bak');

        // Check that content was fixed
        $fixedContent = file_get_contents($testFile);
        $this->assertIsString($fixedContent);
        // The fix adds the non-breaking space before the exclamation mark
        $this->assertStringContainsString('Bonjour' . FrenchGuidelinesChecker::NBSP . '!', $fixedContent);
    }

    public function testQuietMode(): void
    {
        $poContent = <<<PO
            msgid "Hello!"
            msgstr "Bonjour!"
            PO;

        $testFile = $this->createTestPoFile('test-quiet.po', $poContent);
        $result = $this->runCli(['--quiet', $testFile]);

        $this->assertEquals(1, $result['return_code']);
        $this->assertStringNotContainsString('Checking', $result['output']);
        $this->assertStringContainsString('ERROR:', $result['output']);
    }

    public function testNoWarningsFlag(): void
    {
        $poContent = <<<PO
            msgid "Please archive your documents"
            msgstr "Veuillez compresser vos documents"
            PO;

        $testFile = $this->createTestPoFile('test-no-warnings.po', $poContent);

        // First run without --no-warnings to see warnings
        $result1 = $this->runCli([$testFile]);
        $this->assertStringContainsString('WARNING:', $result1['output']);

        // Then run with --no-warnings
        $result2 = $this->runCli(['--no-warnings', $testFile]);
        $this->assertStringNotContainsString('WARNING:', $result2['output']);
    }

    public function testTranslateWithoutApiKey(): void
    {
        $poContent = <<<PO
            msgid "Hello"
            msgstr ""
            PO;

        $testFile = $this->createTestPoFile('test-translate-no-key.po', $poContent);

        // Temporarily unset API key if it exists
        $originalApiKey = getenv('OPENAI_API_KEY');
        if ($originalApiKey) {
            putenv('OPENAI_API_KEY=');
        }

        $result = $this->runCli(['--translate', $testFile]);

        // Restore original API key
        if ($originalApiKey) {
            putenv('OPENAI_API_KEY=' . $originalApiKey);
        }

        $this->assertEquals(1, $result['return_code']);
        $this->assertStringContainsString('OPENAI_API_KEY environment variable is not set', $result['output']);
    }

    public function testRetranslateGlossaryFlag(): void
    {
        $poContent = <<<PO
            # glossary-review: 'archive' → 'archive ou archiver'
            msgid "Please archive your documents"
            msgstr "Veuillez compresser vos documents"

            msgid "Another message"
            msgstr ""
            PO;

        $testFile = $this->createTestPoFile('test-retranslate-glossary.po', $poContent);

        // Temporarily unset API key if it exists
        $originalApiKey = getenv('OPENAI_API_KEY');
        if ($originalApiKey) {
            putenv('OPENAI_API_KEY=');
        }
        
        // Test that --retranslate-glossary automatically enables translation (requires API key)
        $result = $this->runCli(['--retranslate-glossary', $testFile]);
        
        // Restore original API key
        if ($originalApiKey) {
            putenv('OPENAI_API_KEY=' . $originalApiKey);
        }
        
        // Should fail because --retranslate-glossary forces translation mode which needs API key
        $this->assertEquals(1, $result['return_code']);
        $this->assertStringContainsString('OPENAI_API_KEY environment variable is not set', $result['output']);
    }

    public function testRetranslateGlossaryFlagBehavior(): void
    {
        $poContent = <<<PO
# glossary-review: 'archive' → 'archive ou archiver'
msgid "Please archive your documents"
msgstr "Veuillez compresser vos documents"

msgid "Another message without glossary comment"
msgstr ""
PO;
        
        $testFile = $this->createTestPoFile('test-retranslate-glossary-behavior.po', $poContent);
        
        // Test that --retranslate-glossary works without explicit --translate flag
        // (This will fail due to missing API key, but proves the flag enables translation)
        $originalApiKey = getenv('OPENAI_API_KEY');
        if ($originalApiKey) {
            putenv('OPENAI_API_KEY=');
        }
        
        $result = $this->runCli(['--retranslate-glossary', $testFile]);
        
        // Restore original API key
        if ($originalApiKey) {
            putenv('OPENAI_API_KEY=' . $originalApiKey);
        }
        
        // Proves that --retranslate-glossary automatically enables translation
        $this->assertEquals(1, $result['return_code']);
        $this->assertStringContainsString('OPENAI_API_KEY environment variable is not set', $result['output']);
    }

    public function testInvalidEngine(): void
    {
        $poContent = <<<PO
            msgid "Hello"
            msgstr ""
            PO;

        $testFile = $this->createTestPoFile('test-invalid-engine.po', $poContent);
        $result = $this->runCli(['--translate', '--engine=invalid', $testFile]);

        $this->assertEquals(1, $result['return_code']);
        $this->assertStringContainsString('Unknown engine', $result['output']);
    }

    public function testNonExistentFile(): void
    {
        $result = $this->runCli(['non-existent-file.po']);

        $this->assertEquals(1, $result['return_code']);
        $this->assertStringContainsString('File not found', $result['output']);
    }

    public function testMultipleFiles(): void
    {
        $poContent1 = <<<PO
            msgid "Hello!"
            msgstr "Bonjour!"
            PO;

        $poContent2 = <<<PO
            msgid "Goodbye!"
            msgstr "Au revoir!"
            PO;

        $testFile1 = $this->createTestPoFile('test-multi1.po', $poContent1);
        $testFile2 = $this->createTestPoFile('test-multi2.po', $poContent2);

        $result = $this->runCli([$testFile1, $testFile2]);

        $this->assertEquals(1, $result['return_code']);
        // Should process both files (without --quiet so we see the filenames)
        $this->assertStringContainsString('test-multi1.po', $result['output']);
        $this->assertStringContainsString('test-multi2.po', $result['output']);
    }

    public function testLanguageDetectionFromFilename(): void
    {
        $poContent = <<<PO
            msgid "Hello"
            msgstr "Hola"
            PO;

        $testFile = $this->createTestPoFile('plugin-es.po', $poContent);
        $result = $this->runCli([$testFile]);

        $this->assertEquals(0, $result['return_code']); // No typography errors for Spanish
        $this->assertStringContainsString('Language: Spanish', $result['output']);
    }

    public function testGermanLanguageDetection(): void
    {
        $poContent = <<<PO
            msgid "Hello"
            msgstr "Hallo"
            PO;

        $testFile = $this->createTestPoFile('plugin-de.po', $poContent);
        $result = $this->runCli([$testFile]);

        $this->assertEquals(0, $result['return_code']); // No typography errors for German
        $this->assertStringContainsString('Language: German', $result['output']);
    }

    public function testReadOnlyFile(): void
    {
        $poContent = <<<PO
            msgid "Hello!"
            msgstr "Bonjour!"
            PO;

        $testFile = $this->createTestPoFile('test-readonly.po', $poContent);
        chmod($testFile, 0o444); // Make file read-only

        $result = $this->runCli(['--fix', $testFile]);

        chmod($testFile, 0o644); // Restore permissions for cleanup

        $this->assertEquals(1, $result['return_code']);
        $this->assertStringContainsString('Cannot write to file', $result['output']);
    }
}
