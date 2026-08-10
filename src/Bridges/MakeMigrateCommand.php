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
     * @param string $migrationsDir Directory generated .sql migration files are written into.
     * @param class-string[] $entityClasses Fully-qualified entity class names to compare.
     * @param ?string $entitiesDir If given, entity classes are also loaded from this directory
     *        (recursively) and merged into $entityClasses — see scanEntitiesDir().
     * @param DbTypeDriver $driver SQL dialect used to generate/compare migration SQL.
     */
    public function __construct(
        protected AbstractDatabase $database,
        protected string           $migrationsDir,
        protected array            $entityClasses = [],
        protected ?string          $entitiesDir = null,
        protected DbTypeDriver     $driver = new MariaDB(),
    ) {
        parent::__construct();
        if ($this->entitiesDir !== null) {
            $this->entityClasses = array_values(array_unique(array_merge(
                $this->entityClasses,
                self::scanEntitiesDir($this->entitiesDir),
            )));
        }
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'empty - show db diff, else create migration');
    }

    /**
     * Recursively scans a directory for entity classes and returns their fully-qualified class
     * names. Each file's real namespace/class name is determined by parsing it — no PSR-4 naming
     * convention is assumed, so entities may live in nested subdirectories with different
     * namespaces. Abstract classes (e.g. a shared base entity) are skipped; interfaces, traits and
     * enums are ignored (they never match as a class).
     *
     * @return class-string[]
     */
    public static function scanEntitiesDir(string $dir): array
    {
        // Build the class => file map first, then resolve classes through a temporary autoloader
        // rather than require_once-ing files in filesystem iteration order — a subclass file may
        // otherwise be encountered (and required) before the file declaring its parent class.
        $classMap = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $className = self::findClassInFile($file->getPathname());
            if ($className !== null) $classMap[$className] = $file->getPathname();
        }

        $autoloader = static function (string $class) use ($classMap): void {
            if (isset($classMap[$class])) require_once $classMap[$class];
        };
        spl_autoload_register($autoloader);
        try {
            $classes = [];
            foreach ($classMap as $className => $path) {
                if (!class_exists($className)) continue;
                if ((new \ReflectionClass($className))->isAbstract()) continue;
                $classes[] = $className;
            }

            return $classes;
        } finally {
            spl_autoload_unregister($autoloader);
        }
    }

    /**
     * Determines the fully-qualified class name declared in a PHP file by tokenizing it, without
     * loading/executing the file. Returns null if the file declares no class (only an interface,
     * trait, enum, or nothing).
     */
    private static function findClassInFile(string $path): ?string
    {
        $tokens = token_get_all(file_get_contents($path));
        $namespace = '';
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) continue;

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                for ($i++; $i < $count && $tokens[$i] !== ';'; $i++) {
                    if (is_array($tokens[$i]) && $tokens[$i][0] !== T_WHITESPACE && $tokens[$i][0] !== T_COMMENT) {
                        $namespace .= $tokens[$i][1];
                    }
                }
                continue;
            }

            if ($token[0] !== T_CLASS) continue;

            // skip `Foo::class`
            $prev = null;
            for ($p = $i - 1; $p >= 0; $p--) {
                if (is_array($tokens[$p]) && in_array($tokens[$p][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
                $prev = $tokens[$p];
                break;
            }
            if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) continue;

            for ($j = $i + 1; $j < $count; $j++) {
                if (!is_array($tokens[$j])) break;
                if ($tokens[$j][0] === T_WHITESPACE || $tokens[$j][0] === T_COMMENT) continue;
                if ($tokens[$j][0] === T_STRING) {
                    return $namespace !== '' ? $namespace . '\\' . $tokens[$j][1] : $tokens[$j][1];
                }
                break;
            }
        }

        return null;
    }

    /**
     * Generates the diff SQL for each entity individually (rather than batching them into one
     * DbDeploy::syncTables() call) so that if generation fails, the exception can identify exactly
     * which entity caused it.
     */
    protected function dbDiff(): string
    {
        $deploy = new DbDeploy($this->driver);
        $reader = new DbSchemaReader($this->database, $this->driver);

        $sql = '';
        foreach ($this->entityClasses as $className) {
            try {
                $ti = TableInfo::get($className);
                $sql .= $deploy->syncTables([[$ti, $reader->getTableInfo($ti->tableName)]]);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Migration generation failed for entity '$className': {$e->getMessage()}", 0, $e);
            }
        }

        return $sql;
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
