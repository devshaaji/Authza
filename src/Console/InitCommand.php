<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize Authza configuration file')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing configuration file'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output file path',
                'authza.config.php'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $outputPath = $input->getOption('output');
        $force = $input->getOption('force');

        // Resolve to absolute path
        if (!str_starts_with($outputPath, '/') && !preg_match('/^[A-Z]:/i', $outputPath)) {
            $outputPath = getcwd() . DIRECTORY_SEPARATOR . $outputPath;
        }

        // Check if there's already a remembered config
        $existingRememberedConfig = ConfigPathResolver::getRememberedConfigPath();
        if ($existingRememberedConfig !== null && !$force) {
            $helper->warning("A configuration is already set up: {$existingRememberedConfig}");
            $output->writeln('');
            
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<question>Do you want to replace it with a new configuration? [y/N]</question> ',
                false
            );
            
            if (!$questionHelper->ask($input, $output, $question)) {
                $helper->info('Operation cancelled. Existing configuration preserved.');
                return Command::SUCCESS;
            }
            $output->writeln('');
        }

        if (file_exists($outputPath) && !$force) {
            $helper->error("Configuration file already exists: {$outputPath}");
            $helper->info('Use --force to overwrite');
            return Command::FAILURE;
        }

        $configTemplate = $this->getConfigTemplate();

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $helper->error("Failed to create directory: {$dir}");
                return Command::FAILURE;
            }
        }

        if (file_put_contents($outputPath, $configTemplate) === false) {
            $helper->error("Failed to write configuration file: {$outputPath}");
            return Command::FAILURE;
        }

        // Remember the config path for future CLI calls
        if (ConfigPathResolver::rememberConfigPath($outputPath)) {
            $helper->success("Configuration file created: {$outputPath}");
            $helper->info("Config path saved - you can now run commands without --config");
        } else {
            $helper->success("Configuration file created: {$outputPath}");
            $helper->warning("Could not save config path - you'll need to use --config option");
        }

        $output->writeln('');
        $output->writeln('<comment>Next steps:</comment>');
        $output->writeln('  1. Edit the configuration file to set up your cache and logger');
        $output->writeln('  2. Register your policy classes explicitly in policies.register');
        $output->writeln('  3. Add your DSL rule files to dsl.files');
        $output->writeln('  4. Run: vendor/bin/authza --config=authza.config.php list');
        $output->writeln('');
        $output->writeln('<info>Security notes:</info>');
        $output->writeln('  - Policies must be explicitly registered (no auto-discovery)');
        $output->writeln('  - DSL files must be explicitly listed');
        $output->writeln('  - Use --config option in production for explicit config path');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function getConfigTemplate(): string
    {
        return <<<'PHP'
<?php

/**
 * Authza Configuration File
 * 
 * This file configures the Authza authorization engine for both
 * CLI commands and application code.
 * 
 * SECURITY NOTES:
 * - All policies must be explicitly registered (no auto-discovery)
 * - All DSL files must be explicitly listed
 * - Use --config CLI option in production environments
 * 
 * @see https://github.com/authza/authza
 */

use Authza\Adapters\Cache\ArrayCache;
use Authza\Adapters\Cache\FileCache;

return [
    /**
     * Cache Configuration (PSR-16 SimpleCache) - Optional
     * 
     * If not provided, the system will evaluate policies directly without caching.
     * For production, providing a cache instance improves performance.
     * 
     * Options:
     * - Use Authza\Adapters\Cache\ArrayCache for testing/development
     * - Use Authza\Adapters\Cache\FileCache for simple file-based caching
     * - Use your own PSR-16 implementation (Redis, Memcached, etc.)
     */
    'cache' => [
        // 'instance' => new ArrayCache(),
        // 'instance' => new FileCache(__DIR__ . '/var/cache/authza'),
    ],

    /**
     * Logger Configuration (PSR-3 Logger) - Optional
     * 
     * Inject your own PSR-3 logger for audit logging.
     * If not provided, a NullLogger is used.
     */
    'logger' => [
        // 'instance' => new \Monolog\Logger('authza'),
    ],

    /**
     * Permission Graph Storage
     * 
     * Path to persist the computed permission graph.
     */
    'graph' => [
        'storage' => __DIR__ . '/var/storage/authza_graph.json',
    ],

    /**
     * PHP Policy Classes (Explicit Registration)
     * 
     * SECURITY: All policies must be explicitly registered.
     * Auto-discovery is disabled to prevent loading malicious code.
     * 
     * Format: 'resource_type' => PolicyClass::class
     *    or:  'resource_type' => new PolicyClass()
     */
    'policies' => [
        'register' => [
            // Example: Register your policy classes
            // 'user' => \App\Policies\UserPolicy::class,
            // 'invoice' => \App\Policies\InvoicePolicy::class,
            // 'document' => new \App\Policies\DocumentPolicy($someDependency),
        ],
    ],

    /**
     * DSL Policy Files (Explicit File List)
     * 
     * SECURITY: All DSL files must be explicitly listed.
     * Glob patterns are supported but must be explicitly configured.
     */
    'dsl' => [
        'files' => [
            // Example: List your DSL rule files
            // __DIR__ . '/rules/rbac.json',
            // __DIR__ . '/rules/permissions.dsl',
            // __DIR__ . '/rules/*.json',  // Glob pattern
        ],
    ],

    /**
     * Bootstrap Callback (Optional)
     * 
     * For advanced customization after the container is initialized.
     * Receives the ServiceContainer instance.
     */
    // 'bootstrap' => function(\Authza\Console\ServiceContainer $container) {
    //     // Custom initialization code
    // },
];
PHP;
    }
}
