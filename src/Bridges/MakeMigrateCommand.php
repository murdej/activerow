<?php declare(strict_types=1);

namespace Murdej\ActiveRow\Bridges;

use Murdej\ActiveRow\AbstractDatabase;
use Murdej\ActiveRow\Interfaces\DbTypeDriver;
use Murdej\ActiveRow\Migrations\DbDeploy;
use Murdej\ActiveRow\Migrations\DbSchemaReader;
use Murdej\ActiveRow\Migrations\MariaDB;
use Murdej\ActiveRow\TableInfo;
use Nette\Utils\Strings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'migrations:by-diff',
    description: 'Creates a migration by comparing the current database and PHP entities.',
)]
class MakeMigrateCommand extends Command
{
    /**
     * @param AbstractDatabase $database Database to compare the entities against.
     * @param class-string[] $entityClasses Fully-qualified entity class names to compare.
     * @param string $migrationsDir Directory generated .sql migration files are written into.
     * @param DbTypeDriver $driver SQL dialect used to generate/compare migration SQL.
     */
    public function __construct(
        protected AbstractDatabase $database,
        protected array $entityClasses,
        protected string $migrationsDir,
        protected DbTypeDriver $driver = new MariaDB(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'empty - show db diff, else create migration');
    }

    /**
     * Scans a directory for entity class files and returns their fully-qualified class names.
     * A convenience helper for building the $entityClasses constructor argument; one class per
     * `*.php` file directly in $dir, named after the file and prefixed with $namespace.
     *
     * @param string[] $exclude Class names (without namespace) to skip, e.g. a shared base class.
     * @return class-string[]
     */
    public static function scanEntitiesDir(string $namespace, string $dir, array $exclude = []): array
    {
        $classes = [];
        foreach (scandir($dir) as $file) {
            if (!str_ends_with($file, '.php')) continue;
            $className = substr($file, 0, -4);
            if (in_array($className, $exclude, true)) continue;
            $classes[] = rtrim($namespace, '\\') . '\\' . $className;
        }

        return $classes;
    }

    protected function dbDiff(): string
    {
        $deploy = new DbDeploy($this->driver);
        $reader = new DbSchemaReader($this->database, $this->driver);

        $pairs = [];
        foreach ($this->entityClasses as $className) {
            $ti = TableInfo::get($className);
            $pairs[] = [$ti, $reader->getTableInfo($ti->tableName)];
        }

        return $deploy->syncTables($pairs);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        if ($name) {
            $fileName = rtrim($this->migrationsDir, '/') . '/' . date('Y-m-d-His-') . Strings::webalize($name) . '.sql';
            file_put_contents($fileName, $this->dbDiff());
            $io->info('Saved migration file: ' . $fileName);
        } else {
            $io->info('No migration name specified, migration will only be displayed');
            $io->writeln($this->dbDiff());
        }

        return Command::SUCCESS;
    }
}
