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
use Topdata\TopdataFoundationSW6\Service\PluginHelperService;
use Topdata\TopdataFoundationSW6\Util\CliLogger;

/**
 * Command for overwriting credentials in the configuration store to set up connections to the Topdata staging servers.
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:use-webservice-demo-credentials',
    description: 'Use the demo credentials for the Topdata webservice. It updates the system configuration with the demo credentials.',
)]
class UseWebserviceDemoCredentialsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly Connection          $connection,
        private readonly PluginHelperService $pluginHelperService
    ) {
        parent::__construct();
    }

    /**
     * Standard flag configuration.
     */
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overriding of credentials that already exist in the database');
    }

    /**
     * Initializes the console output adapter.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        CliLogger::setCliStyle($this->cliStyle);
    }

    /**
     * Script routine to inject values.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getOption('force');

        if (!$this->pluginHelperService->isWebserviceConnectorPluginAvailable()) {
            CliLogger::error('The Topdata Webservice Connector plugin is not installed.');
            return Command::FAILURE;
        }

        if ($this->_doCredentialsExist()) {
            if (!$force) {
                CliLogger::error('Credentials already exist. Use --force to override.');
                return Command::FAILURE;
            }
            $this->_deleteExistingCredentials();
        }

        $this->_insertCredentials();
        CliLogger::success('Credentials set');

        return Command::SUCCESS;
    }

    /**
     * Clears credentials config records.
     */
    private function _deleteExistingCredentials(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config 
            WHERE configuration_key IN (
                :username,
                :apiKey,
                :apiSalt
            )', [
            'username' => 'TopdataConnectorSW6.config.apiUid',
            'apiKey'   => 'TopdataConnectorSW6.config.apiKey',
            'apiSalt'  => 'TopdataConnectorSW6.config.apiSalt',
        ]);
    }

    /**
     * Injects standard API configuration staging blocks.
     */
    private function _insertCredentials(): void
    {
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $configs = [
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiUid',
                'configuration_value' => json_encode(['_value' => '6']),
                'created_at'          => $now
            ],
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiPassword',
                'configuration_value' => json_encode(['_value' => 'nTI9kbsniVWT13Ns']),
                'created_at'          => $now
            ],
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiSecurityKey',
                'configuration_value' => json_encode(['_value' => 'oateouq974fpby5t6ldf8glzo85mr9t6aebozrox']),
                'created_at'          => $now
            ],
        ];

        foreach ($configs as $config) {
            $this->connection->insert('system_config', $config);
        }
    }

    /**
     * Resolves whether relevant database settings are already present.
     */
    private function _doCredentialsExist(): bool
    {
        $existingCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM system_config 
                WHERE configuration_key IN (
                    :username,
                    :apiKey,
                    :apiSalt
                )',
            [
                'username' => 'TopdataConnectorSW6.config.apiUid',
                'apiKey'   => 'TopdataConnectorSW6.config.apiKey',
                'apiSalt'  => 'TopdataConnectorSW6.config.apiSalt',
            ]
        );

        return $existingCount > 0;
    }
}
