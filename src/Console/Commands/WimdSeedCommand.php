<?php
namespace Wimd\Console\Commands;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Console\ConfirmableTrait;
use Wimd\Facades\Wimd;
class WimdSeedCommand extends SeedCommand
{
    use ConfirmableTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'db:wimd-seed';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with monitoring using WIMD (Where Is My Data)';
    /**
     * Create a new database seed command instance.
     *
     * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
     * @return void
     */
    public function __construct(Resolver $resolver)
    {
        parent::__construct($resolver);
        // Add WIMD specific options
        $this->addOption(
            'wimd-mode',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
            'WIMD monitoring mode (light or full)',
            config('wimd.mode', 'full')
        );
    }
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return 1;
        }
        $this->components->info('Starting WIMD monitoring...');
        // Set WIMD mode from command line option
        $mode = $this->option('wimd-mode');
        if (in_array($mode, ['light', 'full'])) {
            $this->components->info("Using WIMD mode: $mode");
            Wimd::setMode($mode);
        } else {
            $this->components->warn("Invalid WIMD mode: $mode. Using default mode: " . config('wimd.mode', 'full'));
        }
        // Set output for WIMD
        Wimd::setOutput($this->output);
        $previousConnections = $this->setUpDatabaseConnection();
        // Run the DatabaseSeeder...
        $this->components->task('Seeding database', function () {
            // Use the parent class method instead of a non-existent runSeeder method
            return parent::handle() === 0;
        });
        // Display the report
        Wimd::displayReport();
        // Reset the connection if needed
        if ($previousConnections) {
            $this->restoreDatabaseConnection($previousConnections);
        }
        return 0;
    }
    /**
     * Set up the database connection to use.
     *
     * @return array|null
     */
    protected function setUpDatabaseConnection()
    {
        $previousConnections = null;
        $connection = $this->option('database');
        if ($connection) {
            $previousConnections = [
                'database.connections.wimd_temp' => config('database.connections.' . $connection),
                'database.default' => config('database.default'),
            ];
            // Use the specified connection for the seeders
            $this->resolver->setDefaultConnection($connection);
        }
        return $previousConnections;
    }
    /**
     * Restore the previous database connection.
     *
     * @param array $previousConnections
     * @return void
     */
    protected function restoreDatabaseConnection(array $previousConnections)
    {
        if (isset($previousConnections['database.default'])) {
            $this->resolver->setDefaultConnection($previousConnections['database.default']);
        }
    }
}
