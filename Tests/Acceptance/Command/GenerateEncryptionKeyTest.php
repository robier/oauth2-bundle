<?php

namespace Trikoder\Bundle\OAuth2Bundle\Tests\Acceptance\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Trikoder\Bundle\OAuth2Bundle\Command\GenerateEncryptionKeyCommand;

final class GenerateEncryptionKeyTest extends KernelTestCase
{
    /**
     * @var Application
     */
    private $app;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->app = new Application();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->app = null;

        if (file_exists('/tmp/.env')) {
            unlink('/tmp/.env');
        }
    }

    public function testConsoleRegisteredProperly(): void
    {
        $console = static::$container->get('trikoder.oauth2.command.generate_encryption_key');

        $this->assertInstanceOf(GenerateEncryptionKeyCommand::class, $console);
    }

    public function testGeneratingKey(): void
    {
        $response = $this->runCommand(new GenerateEncryptionKeyCommand('/tmp', new Filesystem()), ['--dry-run' => true]);

        $this->assertRandomGeneratedKey($response);
    }

    public function testGeneratingKeyForNotExistingEnvFile(): void
    {
        $response = $this->runCommand(new GenerateEncryptionKeyCommand('/tmp', new Filesystem()));

        $this->assertRandomGeneratedKey($response);

        $this->assertStringEndsWith("File .env not found in project root so encryption key is not persisted!\n", $response);
    }

    public function testGeneratingKeyForExistingEnvFileWithoutVariableDefined(): void
    {
        file_put_contents('/tmp/.env', '');

        $response = $this->runCommand(new GenerateEncryptionKeyCommand('/tmp', new Filesystem()));

        $this->assertRandomGeneratedKey($response);

        $this->assertStringEndsWith("Encryption key generated and persisted in .env file!\n", $response);
    }

    public function testGeneratingKeyForExistingEnvFileWithVariableDefined(): void
    {
        file_put_contents('/tmp/.env', 'TRIKODER_OAUTH2_ENCRYPTION_KEY=test');

        $response = $this->runCommand(new GenerateEncryptionKeyCommand('/tmp', new Filesystem()));

        $this->assertRandomGeneratedKey($response);

        $this->assertStringEndsWith("Encryption key already exists, use --force flag to overwrite!\n", $response);
    }

    public function testGeneratingKeyForExistingEnvFileWithVariableDefinedForce(): void
    {
        file_put_contents('/tmp/.env', 'TRIKODER_OAUTH2_ENCRYPTION_KEY=test');

        $response = $this->runCommand(new GenerateEncryptionKeyCommand('/tmp', new Filesystem()), ['--force' => true]);

        $this->assertRandomGeneratedKey($response);

        $this->assertStringEndsWith("Old encryption key value replaced with new one!\n", $response);
    }

    private function assertRandomGeneratedKey(string $response): void
    {
        $this->assertRegExp('/random generated key: .*/i', $response);
    }

    private function runCommand(Command $command, array $params = []): string
    {
        $this->app->add($command);

        $command = $this->app->find($command->getName());
        $commandTester = new CommandTester($command);

        $params['command'] = $command->getName();

        $commandTester->execute($params);

        // the output of the command in the console
        return $commandTester->getDisplay();
    }
}
