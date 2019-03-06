<?php

namespace Trikoder\Bundle\OAuth2Bundle\Command;

use phpseclib\Crypt\RSA;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

final class GenerateRsaPairCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'trikoder:oauth2:generate-rsa';

    /**
     * @var int
     */
    private const DEFAULT_LENGTH_OF_PRIVATE_KEY = 4096;

    /**
     * @var string[]
     */
    private $paths = [];

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var RSA
     */
    private $rsa;

    public function __construct(string $privateKeyPath, string $publicKeyPath, Filesystem $filesystem, RSA $rsa)
    {
        parent::__construct(null);

        $this->paths = [
            'publickey' => $publicKeyPath,
            'privatekey' => $privateKeyPath,
        ];

        $this->filesystem = $filesystem;
        $this->rsa = $rsa;
    }

    public function configure(): void
    {
        $this
            ->setDescription('Generates RSA public/private pair')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite keys if they already exist'
            )
            ->addOption(
                'length',
                'l',
                InputOption::VALUE_OPTIONAL,
                'The length of the private key',
                static::DEFAULT_LENGTH_OF_PRIVATE_KEY
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->keysExists() && false === $input->getOption('force')) {
            $output->writeln('<error>Encryption keys already exist. Use the --force|-f option to overwrite them.</error>');

            return 1;
        }

        $keys = $this->rsa->createKey($input->getOption('length'));

        foreach (['publickey', 'privatekey'] as $keyName) {
            $this->filesystem->dumpFile($this->paths[$keyName], $keys[$keyName]);
        }

        $output->writeln('<info>Encryption keys generated successfully.</info>');

        return 0;
    }

    private function keysExists(): bool
    {
        foreach ($this->paths as $path) {
            if ($this->filesystem->exists($path)) {
                return true;
            }
        }

        return false;
    }
}
