<?php

namespace Trikoder\Bundle\OAuth2Bundle\Tests\Acceptance\Command;

use phpseclib\Crypt\RSA;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Trikoder\Bundle\OAuth2Bundle\Command\GenerateRsaPair;

final class GenerateRsaPairTest extends KernelTestCase
{
    private const TEST_DATA = [
        'content' => [
            'publickey' => 'public key data',
            'privatekey' => 'private key data',
        ],
        'path' => [
            'publickey' => '/tmp/public.key',
            'privatekey' => '/tmp/private.key',
        ],
    ];

    /**
     * @var Application
     */
    private $app;

    /**
     * @return void
     */
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

        foreach (static::TEST_DATA['path'] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testConsoleRegisteredProperly(): void
    {
        $console = static::$container->get('trikoder.oauth2.command.generate_rsa');

        $this->assertInstanceOf(GenerateRsaPair::class, $console);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGeneratingRsaPair(): void
    {
        foreach (static::TEST_DATA['path'] as $path) {
            $this->assertFileNotExists($path);
        }

        $rsa = $this->mockRSA(static::TEST_DATA['content']);

        $this->runCommand(new GenerateRsaPair(
            static::TEST_DATA['path']['privatekey'],
            static::TEST_DATA['path']['publickey'],
            new Filesystem(),
            $rsa));

        foreach (['publickey', 'privatekey'] as $keyName) {
            $path = static::TEST_DATA['path'][$keyName];
            $content = static::TEST_DATA['content'][$keyName];

            $this->assertFileExists($path);

            $this->assertFileContentEquals($content, $path);

            $this->assertFileChmod($path, 0600);
        }
    }

    public function testNotOverwritingAlreadyExistingRsaPairFiles(): void
    {
        foreach (static::TEST_DATA['path'] as $key => $path) {
            file_put_contents($path, static::TEST_DATA['content'][$key]);
        }

        $this->runCommand(new GenerateRsaPair(
            static::TEST_DATA['path']['privatekey'],
            static::TEST_DATA['path']['publickey'],
            new Filesystem(),
            new RSA()));

        foreach (static::TEST_DATA['path'] as $key => $path) {
            $this->assertFileContentEquals(static::TEST_DATA['content'][$key], $path);
        }
    }

    public function testOverwritingAlreadyExistingRsaPairFiles(): void
    {
        foreach (static::TEST_DATA['path'] as $key => $path) {
            file_put_contents($path, static::TEST_DATA['content'][$key]);
        }

        $testContent = [
            'privatekey' => 'new private test data',
            'publickey' => 'new public test data',
        ];

        $rsa = $this->mockRSA($testContent);

        $this->runCommand(new GenerateRsaPair(
            static::TEST_DATA['path']['privatekey'],
            static::TEST_DATA['path']['publickey'],
            new Filesystem(),
            $rsa),
            ['--force' => true]);

        foreach (static::TEST_DATA['path'] as $key => $path) {
            $this->assertFileContentEquals($testContent[$key], $path);
        }
    }

    private function mockRSA(array $returnValue): RSA
    {
        /** @var RSA|PHPUnit_Framework_MockObject_MockObject $rsa */
        $rsa = $this->createMock(RSA::class);
        $rsa->method('createKey')->willReturn($returnValue);

        return $rsa;
    }

    /**
     * @param string $path
     * @param int $chmod
     */
    private function assertFileChmod(string $path, int $chmod): void
    {
        $octalChmod = octdec(substr(sprintf('%o', fileperms($path)), -4));

        $this->assertSame($chmod, $octalChmod);
    }

    /**
     * @param string $expected
     * @param string $pathToFile
     */
    private function assertFileContentEquals(string $expected, string $pathToFile): void
    {
        $this->assertSame($expected, file_get_contents($pathToFile));
    }

    /**
     * @param Command $command
     * @param array $params
     *
     * @return string
     */
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
