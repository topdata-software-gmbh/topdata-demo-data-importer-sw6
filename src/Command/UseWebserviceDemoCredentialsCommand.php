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

/**
 * This command allows to set the demo credentials for the Topdata webservice connector.
 * It updates the system configuration with the demo credentials.
 * 11/2024 created
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
    )
    {
        parent::__construct();
    }

    /**
     * Deletes existing Topdata webservice credentials from the system_config table.
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
     * Inserts the demo credentials into the system_config table.
     */
    private function _insertCredentials(): void
    {
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        //    * API User ID: 6
        //    * API Password: nTI9kbsniVWT13Ns
        //    * API Security Key: oateouq974fpby5t6ldf8glzo85mr9t6aebozrox

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
     * Configures the command by adding the `force` option.
     */
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overriding of credentials that already exist in the database');
    }

    /**
     * Checks if Topdata webservice credentials already exist in the system_config table.
     */
    private function _doCredentialsExist(): bool
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
                'username' => 'TopdataConnectorSW6.config.apiUid',
                'apiKey'   => 'TopdataConnectorSW6.config.apiKey',
                'apiSalt'  => 'TopdataConnectorSW6.config.apiSalt',
            ]
        );

        return $existingCount > 0;
    }

    /**
     * Executes the command to set the demo credentials for the Topdata webservice connector.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @return int 0 if everything went fine, or an error code.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getOption('force');

        // ---- check if the plugin is installed
        if (!$this->pluginHelperService->isWebserviceConnectorPluginAvailable()) {
            $this->cliStyle->error('The Topdata Webservice Connector plugin is not installed.');
            return Command::FAILURE;
        }

        // ---- check if credentials already exist
        if ($this->_doCredentialsExist()) {
            if (!$force) {
                $this->cliStyle->error('Credentials already exist. Use --force to override.');
                return Command::FAILURE;
            }
            // ---- delete existing credentials if force option is used
            $this->_deleteExistingCredentials();
        }

        // ---- insert the demo credentials
        $this->_insertCredentials();

        $this->cliStyle->success('Credentials set');

        return Command::SUCCESS;
    }

}