<?php

namespace Trikoder\Bundle\OAuth2Bundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

final class GenerateEncryptionKeyCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'trikoder:oauth2:generate-encryption-key';

    /**
     * @var string
     */
    private const ENCRYPTION_KEY_ENV_NAME = 'TRIKODER_OAUTH2_ENCRYPTION_KEY';

    /**
     * @var int
     */
    private const BYTES_LENGTH = 32;

    /**
     * @var string
     */
    private $envFilePath;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(string $envFileDir, Filesystem $filesystem)
    {
        parent::__construct();

        $this->envFilePath = sprintf('%s/.env', $envFileDir);
        $this->filesystem = $filesystem;
    }

    /**
     * @return void
     */
    public function configure(): void
    {
        $this
            ->setDescription('Generates strong encryption key for OAuth2 bundle and persists generated value to .env file if file exists')
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Do not modify any file'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite old encryption key if exists'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $randomEncryptionKey = base64_encode(random_bytes(static::BYTES_LENGTH));

        $output->writeln(sprintf('<info>Random generated key:</info> <comment>%s</comment>', $randomEncryptionKey));

        if ($input->getOption('dry-run')) {
            return 0;
        }

        if (!$this->filesystem->exists($this->envFilePath)) {
            $output->writeln('<error>File .env not found in project root so encryption key is not persisted!</error>');

            return 1;
        }

        $envContent = file_get_contents($this->envFilePath);

        if (false === strpos($envContent, static::ENCRYPTION_KEY_ENV_NAME)) {
            $this->filesystem->appendToFile($this->envFilePath, sprintf('%s="%s"', static::ENCRYPTION_KEY_ENV_NAME, $randomEncryptionKey));

            $output->writeln('<info>Encryption key generated and persisted in .env file!</info>');

            return 0;
        }

        if (!$input->getOption('force')) {
            $output->writeln('<error>Encryption key already exists, use --force flag to overwrite!</error>');

            return 1;
        }

        $pattern = sprintf('/^%s="(.*)"$/imu', static::ENCRYPTION_KEY_ENV_NAME);

        preg_match($pattern, $envContent, $matches);

        $finalVariableValue = sprintf('%s="%s"', static::ENCRYPTION_KEY_ENV_NAME, $randomEncryptionKey);

        $this->filesystem->dumpFile($this->envFilePath, str_replace($matches[0], $finalVariableValue, $envContent));

        $output->writeln('<info>Old encryption key value replaced with new one!</info>');

        return 0;
    }
}
