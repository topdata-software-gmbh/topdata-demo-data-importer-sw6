<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;

/**
 * 11/2024 created
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:use-webservice-demo-credentials',
    description: 'Use the demo credentials for the Topdata webservice. It updates the system configuration with the demo credentials.',
)]
class UseWebserviceDemoCredentialsCommand extends AbstractTopdataCommand
{

    public function __construct(
        private readonly Connection $connection,
    )
    {
        parent::__construct();
    }

    private function deleteExistingCredentials(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config 
            WHERE configuration_key IN (
                :username,
                :apiKey,
                :apiSalt
            )', [
            'username' => 'TopdataConnectorSW6.config.apiUsername',
            'apiKey'   => 'TopdataConnectorSW6.config.apiKey',
            'apiSalt'  => 'TopdataConnectorSW6.config.apiSalt',
        ]);
    }

    private function insertCredentials(): void
    {
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $configs = [
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiUsername',
                'configuration_value' => json_encode(['_value' => '6']),
                'created_at'          => $now
            ],
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiKey',
                'configuration_value' => json_encode(['_value' => 'nTI9kbsniVWT13Ns']),
                'created_at'          => $now
            ],
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiSalt',
                'configuration_value' => json_encode(['_value' => 'oateouq974fpby5t6ldf8glzo85mr9t6aebozrox']),
                'created_at'          => $now
            ],
        ];

        foreach ($configs as $config) {
            $this->connection->insert('system_config', $config);
        }
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overriding of credentials that already exist in the database');
    }

    private function doCredentialsExist(): bool
    {

        // Check if any of the configs exist
        $existingCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM system_config 
                WHERE configuration_key IN (
                    :username,
                    :apiKey,
                    :apiSalt
                )',
            [
                'username' => 'TopdataConnectorSW6.config.apiUsername',
                'apiKey'   => 'TopdataConnectorSW6.config.apiKey',
                'apiSalt'  => 'TopdataConnectorSW6.config.apiSalt',
            ]
        );

        return $existingCount > 0;
    }


    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getOption('force');

        if ($this->doCredentialsExist()) {
            if (!$force) {
                $this->cliStyle->error('Credentials already exist. Use --force to override.');
                return Command::FAILURE;
            }
            $this->deleteExistingCredentials();
        }

        $this->insertCredentials();

        $this->cliStyle->success('Credentials set');

        return Command::SUCCESS;
    }

}
