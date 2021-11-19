<?php /** @noinspection ALL */

// @formatter:off


namespace Illuminate\Database\Migrations {

    /**
     * @SuppressWarnings(PHPMD)
     */
    abstract class Migration
    {
        /**
         * The name of the database connection to use.
         *
         * @var string|null
         */
        protected $connection;

        /**
         * Enables, if supported, wrapping the migration within a transaction.
         *
         * @var bool
         */
        public $withinTransaction = true;

        /**
         * Get the migration connection name.
         *
         * @return string|null
         */
        public function getConnection()
        {
            return $this->connection;
        }
    }
}


namespace Illuminate\Database\Schema {


    /**
     * @SuppressWarnings(PHPMD)
     */
    class Blueprint
    {
        use Macroable;

        /**
         * The table the blueprint describes.
         *
         * @var string
         */
        protected $table;

        /**
         * The prefix of the table.
         *
         * @var string
         */
        protected $prefix;

        /**
         * The columns that should be added to the table.
         *
         * @var \Illuminate\Database\Schema\ColumnDefinition[]
         */
        protected $columns = [];

        /**
         * The commands that should be run for the table.
         *
         * @var \Illuminate\Support\Fluent[]
         */
        protected $commands = [];

        /**
         * The storage engine that should be used for the table.
         *
         * @var string
         */
        public $engine;

        /**
         * The default character set that should be used for the table.
         *
         * @var string
         */
        public $charset;

        /**
         * The collation that should be used for the table.
         *
         * @var string
         */
        public $collation;

        /**
         * Whether to make the table temporary.
         *
         * @var bool
         */
        public $temporary = false;

        /**
         * The column to add new columns after.
         *
         * @var string
         */
        public $after;

        /**
         * Create a new schema blueprint.
         *
         * @param string $table
         * @param \Closure|null $callback
         * @param string $prefix
         * @return void
         */
        public function __construct($table, Closure $callback = null, $prefix = '')
        {
            $this->table = $table;
            $this->prefix = $prefix;

            if (!is_null($callback)) {
                $callback($this);
            }
        }

        /**
         * Execute the blueprint against the database.
         *
         * @param \Illuminate\Database\Connection $connection
         * @param \Illuminate\Database\Schema\Grammars\Grammar $grammar
         * @return void
         */
        public function build(Connection $connection, Grammar $grammar)
        {
            foreach ($this->toSql($connection, $grammar) as $statement) {
                $connection->statement($statement);
            }
        }

        /**
         * Get the raw SQL statements for the blueprint.
         *
         * @param \Illuminate\Database\Connection $connection
         * @param \Illuminate\Database\Schema\Grammars\Grammar $grammar
         * @return array
         */
        public function toSql(Connection $connection, Grammar $grammar)
        {
            $this->addImpliedCommands($grammar);

            $statements = [];

            // Each type of command has a corresponding compiler function on the schema
            // grammar which is used to build the necessary SQL statements to build
            // the blueprint element, so we'll just call that compilers function.
            $this->ensureCommandsAreValid($connection);

            foreach ($this->commands as $command) {
                $method = 'compile' . ucfirst($command->name);

                if (method_exists($grammar, $method) || $grammar::hasMacro($method)) {
                    if (!is_null($sql = $grammar->$method($this, $command, $connection))) {
                        $statements = array_merge($statements, (array)$sql);
                    }
                }
            }

            return $statements;
        }

        /**
         * Ensure the commands on the blueprint are valid for the connection type.
         *
         * @param \Illuminate\Database\Connection $connection
         * @return void
         *
         * @throws \BadMethodCallException
         */
        protected function ensureCommandsAreValid(Connection $connection)
        {
            if ($connection instanceof SQLiteConnection) {
                if ($this->commandsNamed(['dropColumn', 'renameColumn'])->count() > 1) {
                    throw new BadMethodCallException(
                        "SQLite doesn't support multiple calls to dropColumn / renameColumn in a single modification."
                    );
                }

                if ($this->commandsNamed(['dropForeign'])->count() > 0) {
                    throw new BadMethodCallException(
                        "SQLite doesn't support dropping foreign keys (you would need to re-create the table)."
                    );
                }
            }
        }

        /**
         * Get all of the commands matching the given names.
         *
         * @param array $names
         * @return \Illuminate\Support\Collection
         */
        protected function commandsNamed(array $names)
        {
            return collect($this->commands)->filter(function ($command) use ($names) {
                return in_array($command->name, $names);
            });
        }

        /**
         * Add the commands that are implied by the blueprint's state.
         *
         * @param \Illuminate\Database\Schema\Grammars\Grammar $grammar
         * @return void
         */
        protected function addImpliedCommands(Grammar $grammar)
        {
            if (count($this->getAddedColumns()) > 0 && !$this->creating()) {
                array_unshift($this->commands, $this->createCommand('add'));
            }

            if (count($this->getChangedColumns()) > 0 && !$this->creating()) {
                array_unshift($this->commands, $this->createCommand('change'));
            }

            $this->addFluentIndexes();

            $this->addFluentCommands($grammar);
        }

        /**
         * Add the index commands fluently specified on columns.
         *
         * @return void
         */
        protected function addFluentIndexes()
        {
            foreach ($this->columns as $column) {
                foreach (['primary', 'unique', 'index', 'spatialIndex'] as $index) {
                    // If the index has been specified on the given column, but is simply equal
                    // to "true" (boolean), no name has been specified for this index so the
                    // index method can be called without a name and it will generate one.
                    if ($column->{$index} === true) {
                        $this->{$index}($column->name);
                        $column->{$index} = false;

                        continue 2;
                    }

                    // If the index has been specified on the given column, and it has a string
                    // value, we'll go ahead and call the index method and pass the name for
                    // the index since the developer specified the explicit name for this.
                    elseif (isset($column->{$index})) {
                        $this->{$index}($column->name, $column->{$index});
                        $column->{$index} = false;

                        continue 2;
                    }
                }
            }
        }

        /**
         * Add the fluent commands specified on any columns.
         *
         * @param \Illuminate\Database\Schema\Grammars\Grammar $grammar
         * @return void
         */
        public function addFluentCommands(Grammar $grammar)
        {
            foreach ($this->columns as $column) {
                foreach ($grammar->getFluentCommands() as $commandName) {
                    $attributeName = lcfirst($commandName);

                    if (!isset($column->{$attributeName})) {
                        continue;
                    }

                    $value = $column->{$attributeName};

                    $this->addCommand(
                        $commandName, compact('value', 'column')
                    );
                }
            }
        }

        /**
         * Determine if the blueprint has a create command.
         *
         * @return bool
         */
        public function creating()
        {
            return collect($this->commands)->contains(function ($command) {
                return $command->name === 'create';
            });
        }

        /**
         * Indicate that the table needs to be created.
         *
         * @return \Illuminate\Support\Fluent
         */
        public function create()
        {
            return $this->addCommand('create');
        }

        /**
         * Indicate that the table needs to be temporary.
         *
         * @return void
         */
        public function temporary()
        {
            $this->temporary = true;
        }

        /**
         * Indicate that the table should be dropped.
         *
         * @return \Illuminate\Support\Fluent
         */
        public function drop()
        {
            return $this->addCommand('drop');
        }

        /**
         * Indicate that the table should be dropped if it exists.
         *
         * @return \Illuminate\Support\Fluent
         */
        public function dropIfExists()
        {
            return $this->addCommand('dropIfExists');
        }

        /**
         * Indicate that the given columns should be dropped.
         *
         * @param array|mixed $columns
         * @return \Illuminate\Support\Fluent
         */
        public function dropColumn($columns)
        {
            $columns = is_array($columns) ? $columns : func_get_args();

            return $this->addCommand('dropColumn', compact('columns'));
        }

        /**
         * Indicate that the given columns should be renamed.
         *
         * @param string $from
         * @param string $to
         * @return \Illuminate\Support\Fluent
         */
        public function renameColumn($from, $to)
        {
            return $this->addCommand('renameColumn', compact('from', 'to'));
        }

        /**
         * Indicate that the given primary key should be dropped.
         *
         * @param string|array|null $index
         * @return \Illuminate\Support\Fluent
         */
        public function dropPrimary($index = null)
        {
            return $this->dropIndexCommand('dropPrimary', 'primary', $index);
        }

        /**
         * Indicate that the given unique key should be dropped.
         *
         * @param string|array $index
         * @return \Illuminate\Support\Fluent
         */
        public function dropUnique($index)
        {
            return $this->dropIndexCommand('dropUnique', 'unique', $index);
        }

        /**
         * Indicate that the given index should be dropped.
         *
         * @param string|array $index
         * @return \Illuminate\Support\Fluent
         */
        public function dropIndex($index)
        {
            return $this->dropIndexCommand('dropIndex', 'index', $index);
        }

        /**
         * Indicate that the given spatial index should be dropped.
         *
         * @param string|array $index
         * @return \Illuminate\Support\Fluent
         */
        public function dropSpatialIndex($index)
        {
            return $this->dropIndexCommand('dropSpatialIndex', 'spatialIndex', $index);
        }

        /**
         * Indicate that the given foreign key should be dropped.
         *
         * @param string|array $index
         * @return \Illuminate\Support\Fluent
         */
        public function dropForeign($index)
        {
            return $this->dropIndexCommand('dropForeign', 'foreign', $index);
        }

        /**
         * Indicate that the given column and foreign key should be dropped.
         *
         * @param string $column
         * @return \Illuminate\Support\Fluent
         */
        public function dropConstrainedForeignId($column)
        {
            $this->dropForeign([$column]);

            return $this->dropColumn($column);
        }

        /**
         * Indicate that the given indexes should be renamed.
         *
         * @param string $from
         * @param string $to
         * @return \Illuminate\Support\Fluent
         */
        public function renameIndex($from, $to)
        {
            return $this->addCommand('renameIndex', compact('from', 'to'));
        }

        /**
         * Indicate that the timestamp columns should be dropped.
         *
         * @return void
         */
        public function dropTimestamps()
        {
            $this->dropColumn('created_at', 'updated_at');
        }

        /**
         * Indicate that the timestamp columns should be dropped.
         *
         * @return void
         */
        public function dropTimestampsTz()
        {
            $this->dropTimestamps();
        }

        /**
         * Indicate that the soft delete column should be dropped.
         *
         * @param string $column
         * @return void
         */
        public function dropSoftDeletes($column = 'deleted_at')
        {
            $this->dropColumn($column);
        }

        /**
         * Indicate that the soft delete column should be dropped.
         *
         * @param string $column
         * @return void
         */
        public function dropSoftDeletesTz($column = 'deleted_at')
        {
            $this->dropSoftDeletes($column);
        }

        /**
         * Indicate that the remember token column should be dropped.
         *
         * @return void
         */
        public function dropRememberToken()
        {
            $this->dropColumn('remember_token');
        }

        /**
         * Indicate that the polymorphic columns should be dropped.
         *
         * @param string $name
         * @param string|null $indexName
         * @return void
         */
        public function dropMorphs($name, $indexName = null)
        {
            $this->dropIndex($indexName ?: $this->createIndexName('index', ["{$name}_type", "{$name}_id"]));

            $this->dropColumn("{$name}_type", "{$name}_id");
        }

        /**
         * Rename the table to a given name.
         *
         * @param string $to
         * @return \Illuminate\Support\Fluent
         */
        public function rename($to)
        {
            return $this->addCommand('rename', compact('to'));
        }

        /**
         * Specify the primary key(s) for the table.
         *
         * @param string|array $columns
         * @param string|null $name
         * @param string|null $algorithm
         * @return \Illuminate\Support\Fluent
         */
        public function primary($columns, $name = null, $algorithm = null)
        {
            return $this->indexCommand('primary', $columns, $name, $algorithm);
        }

        /**
         * Specify a unique index for the table.
         *
         * @param string|array $columns
         * @param string|null $name
         * @param string|null $algorithm
         * @return \Illuminate\Support\Fluent
         */
        public function unique($columns, $name = null, $algorithm = null)
        {
            return $this->indexCommand('unique', $columns, $name, $algorithm);
        }

        /**
         * Specify an index for the table.
         *
         * @param string|array $columns
         * @param string|null $name
         * @param string|null $algorithm
         * @return \Illuminate\Support\Fluent
         */
        public function index($columns, $name = null, $algorithm = null)
        {
            return $this->indexCommand('index', $columns, $name, $algorithm);
        }

        /**
         * Specify a spatial index for the table.
         *
         * @param string|array $columns
         * @param string|null $name
         * @return \Illuminate\Support\Fluent
         */
        public function spatialIndex($columns, $name = null)
        {
            return $this->indexCommand('spatialIndex', $columns, $name);
        }

        /**
         * Specify a raw index for the table.
         *
         * @param string $expression
         * @param string $name
         * @return \Illuminate\Support\Fluent
         */
        public function rawIndex($expression, $name)
        {
            return $this->index([new Expression($expression)], $name);
        }

        /**
         * Specify a foreign key for the table.
         *
         * @param string|array $columns
         * @param string|null $name
         * @return \Illuminate\Database\Schema\ForeignKeyDefinition
         */
        public function foreign($columns, $name = null)
        {
            $command = new ForeignKeyDefinition(
                $this->indexCommand('foreign', $columns, $name)->getAttributes()
            );

            $this->commands[count($this->commands) - 1] = $command;

            return $command;
        }

        /**
         * Create a new auto-incrementing big integer (8-byte) column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function id($column = 'id')
        {
            return $this->bigIncrements($column);
        }

        /**
         * Create a new auto-incrementing integer (4-byte) column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function increments($column)
        {
            return $this->unsignedInteger($column, true);
        }

        /**
         * Create a new auto-incrementing integer (4-byte) column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function integerIncrements($column)
        {
            return $this->unsignedInteger($column, true);
        }

        /**
         * Create a new auto-incrementing tiny integer (1-byte) column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function tinyIncrements($column)
        {
            return $this->unsignedTinyInteger($column, true);
        }

        /**
         * Create a new auto-incrementing small integer (2-byte) column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function smallIncrements($column)
        {
            return $this->unsignedSmallInteger($column, true);
        }

        /**
         * Create a new auto-incrementing medium integer (3-byte) column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function mediumIncrements($column)
        {
            return $this->unsignedMediumInteger($column, true);
        }

        /**
         * Create a new auto-incrementing big integer (8-byte) column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function bigIncrements($column)
        {
            return $this->unsignedBigInteger($column, true);
        }

        /**
         * Create a new char column on the table.
         *
         * @param string $column
         * @param int|null $length
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function char($column, $length = null)
        {
            $length = $length ?: Builder::$defaultStringLength;

            return $this->addColumn('char', $column, compact('length'));
        }

        /**
         * Create a new string column on the table.
         *
         * @param string $column
         * @param int|null $length
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function string($column, $length = null)
        {
            $length = $length ?: Builder::$defaultStringLength;

            return $this->addColumn('string', $column, compact('length'));
        }

        /**
         * Create a new tiny text column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function tinyText($column)
        {
            return $this->addColumn('tinyText', $column);
        }

        /**
         * Create a new text column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function text($column)
        {
            return $this->addColumn('text', $column);
        }

        /**
         * Create a new medium text column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function mediumText($column)
        {
            return $this->addColumn('mediumText', $column);
        }

        /**
         * Create a new long text column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function longText($column)
        {
            return $this->addColumn('longText', $column);
        }

        /**
         * Create a new integer (4-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @param bool $unsigned
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function integer($column, $autoIncrement = false, $unsigned = false)
        {
            return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
        }

        /**
         * Create a new tiny integer (1-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @param bool $unsigned
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function tinyInteger($column, $autoIncrement = false, $unsigned = false)
        {
            return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
        }

        /**
         * Create a new small integer (2-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @param bool $unsigned
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function smallInteger($column, $autoIncrement = false, $unsigned = false)
        {
            return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
        }

        /**
         * Create a new medium integer (3-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @param bool $unsigned
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function mediumInteger($column, $autoIncrement = false, $unsigned = false)
        {
            return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
        }

        /**
         * Create a new big integer (8-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @param bool $unsigned
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function bigInteger($column, $autoIncrement = false, $unsigned = false)
        {
            return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
        }

        /**
         * Create a new unsigned integer (4-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function unsignedInteger($column, $autoIncrement = false)
        {
            return $this->integer($column, $autoIncrement, true);
        }

        /**
         * Create a new unsigned tiny integer (1-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function unsignedTinyInteger($column, $autoIncrement = false)
        {
            return $this->tinyInteger($column, $autoIncrement, true);
        }

        /**
         * Create a new unsigned small integer (2-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function unsignedSmallInteger($column, $autoIncrement = false)
        {
            return $this->smallInteger($column, $autoIncrement, true);
        }

        /**
         * Create a new unsigned medium integer (3-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function unsignedMediumInteger($column, $autoIncrement = false)
        {
            return $this->mediumInteger($column, $autoIncrement, true);
        }

        /**
         * Create a new unsigned big integer (8-byte) column on the table.
         *
         * @param string $column
         * @param bool $autoIncrement
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function unsignedBigInteger($column, $autoIncrement = false)
        {
            return $this->bigInteger($column, $autoIncrement, true);
        }

        /**
         * Create a new unsigned big integer (8-byte) column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ForeignIdColumnDefinition
         */
        public function foreignId($column)
        {
            return $this->addColumnDefinition(new ForeignIdColumnDefinition($this, [
                'type' => 'bigInteger',
                'name' => $column,
                'autoIncrement' => false,
                'unsigned' => true,
            ]));
        }

        /**
         * Create a foreign ID column for the given model.
         *
         * @param \Illuminate\Database\Eloquent\Model|string $model
         * @param string|null $column
         * @return \Illuminate\Database\Schema\ForeignIdColumnDefinition
         */
        public function foreignIdFor($model, $column = null)
        {
            if (is_string($model)) {
                $model = new $model;
            }

            return $model->getKeyType() === 'int' && $model->getIncrementing()
                ? $this->foreignId($column ?: $model->getForeignKey())
                : $this->foreignUuid($column ?: $model->getForeignKey());
        }

        /**
         * Create a new float column on the table.
         *
         * @param string $column
         * @param int $total
         * @param int $places
         * @param bool $unsigned
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function float($column, $total = 8, $places = 2, $unsigned = false)
        {
            return $this->addColumn('float', $column, compact('total', 'places', 'unsigned'));
        }

        /**
         * Create a new double column on the table.
         *
         * @param string $column
         * @param int|null $total
         * @param int|null $places
         * @param bool $unsigned
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function double($column, $total = null, $places = null, $unsigned = false)
        {
            return $this->addColumn('double', $column, compact('total', 'places', 'unsigned'));
        }

        /**
         * Create a new decimal column on the table.
         *
         * @param string $column
         * @param int $total
         * @param int $places
         * @param bool $unsigned
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function decimal($column, $total = 8, $places = 2, $unsigned = false)
        {
            return $this->addColumn('decimal', $column, compact('total', 'places', 'unsigned'));
        }

        /**
         * Create a new unsigned float column on the table.
         *
         * @param string $column
         * @param int $total
         * @param int $places
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function unsignedFloat($column, $total = 8, $places = 2)
        {
            return $this->float($column, $total, $places, true);
        }

        /**
         * Create a new unsigned double column on the table.
         *
         * @param string $column
         * @param int $total
         * @param int $places
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function unsignedDouble($column, $total = null, $places = null)
        {
            return $this->double($column, $total, $places, true);
        }

        /**
         * Create a new unsigned decimal column on the table.
         *
         * @param string $column
         * @param int $total
         * @param int $places
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function unsignedDecimal($column, $total = 8, $places = 2)
        {
            return $this->decimal($column, $total, $places, true);
        }

        /**
         * Create a new boolean column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function boolean($column)
        {
            return $this->addColumn('boolean', $column);
        }

        /**
         * Create a new enum column on the table.
         *
         * @param string $column
         * @param array $allowed
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function enum($column, array $allowed)
        {
            return $this->addColumn('enum', $column, compact('allowed'));
        }

        /**
         * Create a new set column on the table.
         *
         * @param string $column
         * @param array $allowed
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function set($column, array $allowed)
        {
            return $this->addColumn('set', $column, compact('allowed'));
        }

        /**
         * Create a new json column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function json($column)
        {
            return $this->addColumn('json', $column);
        }

        /**
         * Create a new jsonb column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function jsonb($column)
        {
            return $this->addColumn('jsonb', $column);
        }

        /**
         * Create a new date column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function date($column)
        {
            return $this->addColumn('date', $column);
        }

        /**
         * Create a new date-time column on the table.
         *
         * @param string $column
         * @param int $precision
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function dateTime($column, $precision = 0)
        {
            return $this->addColumn('dateTime', $column, compact('precision'));
        }

        /**
         * Create a new date-time column (with time zone) on the table.
         *
         * @param string $column
         * @param int $precision
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function dateTimeTz($column, $precision = 0)
        {
            return $this->addColumn('dateTimeTz', $column, compact('precision'));
        }

        /**
         * Create a new time column on the table.
         *
         * @param string $column
         * @param int $precision
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function time($column, $precision = 0)
        {
            return $this->addColumn('time', $column, compact('precision'));
        }

        /**
         * Create a new time column (with time zone) on the table.
         *
         * @param string $column
         * @param int $precision
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function timeTz($column, $precision = 0)
        {
            return $this->addColumn('timeTz', $column, compact('precision'));
        }

        /**
         * Create a new timestamp column on the table.
         *
         * @param string $column
         * @param int $precision
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function timestamp($column, $precision = 0)
        {
            return $this->addColumn('timestamp', $column, compact('precision'));
        }

        /**
         * Create a new timestamp (with time zone) column on the table.
         *
         * @param string $column
         * @param int $precision
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function timestampTz($column, $precision = 0)
        {
            return $this->addColumn('timestampTz', $column, compact('precision'));
        }

        /**
         * Add nullable creation and update timestamps to the table.
         *
         * @param int $precision
         * @return void
         */
        public function timestamps($precision = 0)
        {
            $this->timestamp('created_at', $precision)->nullable();

            $this->timestamp('updated_at', $precision)->nullable();
        }

        /**
         * Add nullable creation and update timestamps to the table.
         *
         * Alias for self::timestamps().
         *
         * @param int $precision
         * @return void
         */
        public function nullableTimestamps($precision = 0)
        {
            $this->timestamps($precision);
        }

        /**
         * Add creation and update timestampTz columns to the table.
         *
         * @param int $precision
         * @return void
         */
        public function timestampsTz($precision = 0)
        {
            $this->timestampTz('created_at', $precision)->nullable();

            $this->timestampTz('updated_at', $precision)->nullable();
        }

        /**
         * Add a "deleted at" timestamp for the table.
         *
         * @param string $column
         * @param int $precision
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function softDeletes($column = 'deleted_at', $precision = 0)
        {
            return $this->timestamp($column, $precision)->nullable();
        }

        /**
         * Add a "deleted at" timestampTz for the table.
         *
         * @param string $column
         * @param int $precision
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function softDeletesTz($column = 'deleted_at', $precision = 0)
        {
            return $this->timestampTz($column, $precision)->nullable();
        }

        /**
         * Create a new year column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function year($column)
        {
            return $this->addColumn('year', $column);
        }

        /**
         * Create a new binary column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function binary($column)
        {
            return $this->addColumn('binary', $column);
        }

        /**
         * Create a new uuid column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function uuid($column)
        {
            return $this->addColumn('uuid', $column);
        }

        /**
         * Create a new UUID column on the table with a foreign key constraint.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ForeignIdColumnDefinition
         */
        public function foreignUuid($column)
        {
            return $this->addColumnDefinition(new ForeignIdColumnDefinition($this, [
                'type' => 'uuid',
                'name' => $column,
            ]));
        }

        /**
         * Create a new IP address column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function ipAddress($column)
        {
            return $this->addColumn('ipAddress', $column);
        }

        /**
         * Create a new MAC address column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function macAddress($column)
        {
            return $this->addColumn('macAddress', $column);
        }

        /**
         * Create a new geometry column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function geometry($column)
        {
            return $this->addColumn('geometry', $column);
        }

        /**
         * Create a new point column on the table.
         *
         * @param string $column
         * @param int|null $srid
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function point($column, $srid = null)
        {
            return $this->addColumn('point', $column, compact('srid'));
        }

        /**
         * Create a new linestring column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function lineString($column)
        {
            return $this->addColumn('linestring', $column);
        }

        /**
         * Create a new polygon column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function polygon($column)
        {
            return $this->addColumn('polygon', $column);
        }

        /**
         * Create a new geometrycollection column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function geometryCollection($column)
        {
            return $this->addColumn('geometrycollection', $column);
        }

        /**
         * Create a new multipoint column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function multiPoint($column)
        {
            return $this->addColumn('multipoint', $column);
        }

        /**
         * Create a new multilinestring column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function multiLineString($column)
        {
            return $this->addColumn('multilinestring', $column);
        }

        /**
         * Create a new multipolygon column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function multiPolygon($column)
        {
            return $this->addColumn('multipolygon', $column);
        }

        /**
         * Create a new multipolygon column on the table.
         *
         * @param string $column
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function multiPolygonZ($column)
        {
            return $this->addColumn('multipolygonz', $column);
        }

        /**
         * Create a new generated, computed column on the table.
         *
         * @param string $column
         * @param string $expression
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function computed($column, $expression)
        {
            return $this->addColumn('computed', $column, compact('expression'));
        }

        /**
         * Add the proper columns for a polymorphic table.
         *
         * @param string $name
         * @param string|null $indexName
         * @return void
         */
        public function morphs($name, $indexName = null)
        {
            if (Builder::$defaultMorphKeyType === 'uuid') {
                $this->uuidMorphs($name, $indexName);
            } else {
                $this->numericMorphs($name, $indexName);
            }
        }

        /**
         * Add nullable columns for a polymorphic table.
         *
         * @param string $name
         * @param string|null $indexName
         * @return void
         */
        public function nullableMorphs($name, $indexName = null)
        {
            if (Builder::$defaultMorphKeyType === 'uuid') {
                $this->nullableUuidMorphs($name, $indexName);
            } else {
                $this->nullableNumericMorphs($name, $indexName);
            }
        }

        /**
         * Add the proper columns for a polymorphic table using numeric IDs (incremental).
         *
         * @param string $name
         * @param string|null $indexName
         * @return void
         */
        public function numericMorphs($name, $indexName = null)
        {
            $this->string("{$name}_type");

            $this->unsignedBigInteger("{$name}_id");

            $this->index(["{$name}_type", "{$name}_id"], $indexName);
        }

        /**
         * Add nullable columns for a polymorphic table using numeric IDs (incremental).
         *
         * @param string $name
         * @param string|null $indexName
         * @return void
         */
        public function nullableNumericMorphs($name, $indexName = null)
        {
            $this->string("{$name}_type")->nullable();

            $this->unsignedBigInteger("{$name}_id")->nullable();

            $this->index(["{$name}_type", "{$name}_id"], $indexName);
        }

        /**
         * Add the proper columns for a polymorphic table using UUIDs.
         *
         * @param string $name
         * @param string|null $indexName
         * @return void
         */
        public function uuidMorphs($name, $indexName = null)
        {
            $this->string("{$name}_type");

            $this->uuid("{$name}_id");

            $this->index(["{$name}_type", "{$name}_id"], $indexName);
        }

        /**
         * Add nullable columns for a polymorphic table using UUIDs.
         *
         * @param string $name
         * @param string|null $indexName
         * @return void
         */
        public function nullableUuidMorphs($name, $indexName = null)
        {
            $this->string("{$name}_type")->nullable();

            $this->uuid("{$name}_id")->nullable();

            $this->index(["{$name}_type", "{$name}_id"], $indexName);
        }

        /**
         * Adds the `remember_token` column to the table.
         *
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function rememberToken()
        {
            return $this->string('remember_token', 100)->nullable();
        }

        /**
         * Add a new index command to the blueprint.
         *
         * @param string $type
         * @param string|array $columns
         * @param string $index
         * @param string|null $algorithm
         * @return \Illuminate\Support\Fluent
         */
        protected function indexCommand($type, $columns, $index, $algorithm = null)
        {
            $columns = (array)$columns;

            // If no name was specified for this index, we will create one using a basic
            // convention of the table name, followed by the columns, followed by an
            // index type, such as primary or index, which makes the index unique.
            $index = $index ?: $this->createIndexName($type, $columns);

            return $this->addCommand(
                $type, compact('index', 'columns', 'algorithm')
            );
        }

        /**
         * Create a new drop index command on the blueprint.
         *
         * @param string $command
         * @param string $type
         * @param string|array $index
         * @return \Illuminate\Support\Fluent
         */
        protected function dropIndexCommand($command, $type, $index)
        {
            $columns = [];

            // If the given "index" is actually an array of columns, the developer means
            // to drop an index merely by specifying the columns involved without the
            // conventional name, so we will build the index name from the columns.
            if (is_array($index)) {
                $index = $this->createIndexName($type, $columns = $index);
            }

            return $this->indexCommand($command, $columns, $index);
        }

        /**
         * Create a default index name for the table.
         *
         * @param string $type
         * @param array $columns
         * @return string
         */
        protected function createIndexName($type, array $columns)
        {
            $index = strtolower($this->prefix . $this->table . '_' . implode('_', $columns) . '_' . $type);

            return str_replace(['-', '.'], '_', $index);
        }

        /**
         * Add a new column to the blueprint.
         *
         * @param string $type
         * @param string $name
         * @param array $parameters
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function addColumn($type, $name, array $parameters = [])
        {
            return $this->addColumnDefinition(new ColumnDefinition(
                array_merge(compact('type', 'name'), $parameters)
            ));
        }

        /**
         * Add a new column definition to the blueprint.
         *
         * @param \Illuminate\Database\Schema\ColumnDefinition $definition
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        protected function addColumnDefinition($definition)
        {
            $this->columns[] = $definition;

            if ($this->after) {
                $definition->after($this->after);

                $this->after = $definition->name;
            }

            return $definition;
        }

        /**
         * Add the columns from the callback after the given column.
         *
         * @param string $column
         * @param \Closure $callback
         * @return void
         */
        public function after($column, Closure $callback)
        {
            $this->after = $column;

            $callback($this);

            $this->after = null;
        }

        /**
         * Remove a column from the schema blueprint.
         *
         * @param string $name
         * @return \Illuminate\Database\Schema\Blueprint
         */
        public function removeColumn($name)
        {
            $this->columns = array_values(array_filter($this->columns, function ($c) use ($name) {
                return $c['name'] != $name;
            }));

            return $this;
        }

        /**
         * Add a new command to the blueprint.
         *
         * @param string $name
         * @param array $parameters
         * @return \Illuminate\Support\Fluent
         */
        protected function addCommand($name, array $parameters = [])
        {
            $this->commands[] = $command = $this->createCommand($name, $parameters);

            return $command;
        }

        /**
         * Create a new Fluent command.
         *
         * @param string $name
         * @param array $parameters
         * @return \Illuminate\Support\Fluent
         */
        protected function createCommand($name, array $parameters = [])
        {
            return new Fluent(array_merge(compact('name'), $parameters));
        }

        /**
         * Get the table the blueprint describes.
         *
         * @return string
         */
        public function getTable()
        {
            return $this->table;
        }

        /**
         * Get the columns on the blueprint.
         *
         * @return \Illuminate\Database\Schema\ColumnDefinition[]
         */
        public function getColumns()
        {
            return $this->columns;
        }

        /**
         * Get the commands on the blueprint.
         *
         * @return \Illuminate\Support\Fluent[]
         */
        public function getCommands()
        {
            return $this->commands;
        }

        /**
         * Get the columns on the blueprint that should be added.
         *
         * @return \Illuminate\Database\Schema\ColumnDefinition[]
         */
        public function getAddedColumns()
        {
            return array_filter($this->columns, function ($column) {
                return !$column->change;
            });
        }

        /**
         * Get the columns on the blueprint that should be changed.
         *
         * @return \Illuminate\Database\Schema\ColumnDefinition[]
         */
        public function getChangedColumns()
        {
            return array_filter($this->columns, function ($column) {
                return (bool)$column->change;
            });
        }

        /**
         * Determine if the blueprint has auto-increment columns.
         *
         * @return bool
         */
        public function hasAutoIncrementColumn()
        {
            return !is_null(collect($this->getAddedColumns())->first(function ($column) {
                return $column->autoIncrement === true;
            }));
        }

        /**
         * Get the auto-increment column starting values.
         *
         * @return array
         */
        public function autoIncrementingStartingValues()
        {
            if (!$this->hasAutoIncrementColumn()) {
                return [];
            }

            return collect($this->getAddedColumns())->mapWithKeys(function ($column) {
                return $column->autoIncrement === true
                    ? [$column->name => $column->get('startingValue', $column->get('from'))]
                    : [$column->name => null];
            })->filter()->all();
        }
    }

    /**
     * @method $this after(string $column) Place the column "after" another column (MySQL)
     * @method $this always() Used as a modifier for generatedAs() (PostgreSQL)
     * @method $this autoIncrement() Set INTEGER columns as auto-increment (primary key)
     * @method $this change() Change the column
     * @method $this charset(string $charset) Specify a character set for the column (MySQL)
     * @method $this collation(string $collation) Specify a collation for the column (MySQL/PostgreSQL/SQL Server)
     * @method $this comment(string $comment) Add a comment to the column (MySQL/PostgreSQL)
     * @method $this default(mixed $value) Specify a "default" value for the column
     * @method $this first() Place the column "first" in the table (MySQL)
     * @method $this generatedAs(string|Expression $expression = null) Create a SQL compliant identity column (PostgreSQL)
     * @method $this index(string $indexName = null) Add an index
     * @method $this nullable(bool $value = true) Allow NULL values to be inserted into the column
     * @method $this persisted() Mark the computed generated column as persistent (SQL Server)
     * @method $this primary() Add a primary index
     * @method $this spatialIndex() Add a spatial index
     * @method $this startingValue(int $startingValue) Set the starting value of an auto-incrementing field (MySQL/PostgreSQL)
     * @method $this storedAs(string $expression) Create a stored generated column (MySQL/PostgreSQL/SQLite)
     * @method $this type(string $type) Specify a type for the column
     * @method $this unique(string $indexName = null) Add a unique index
     * @method $this unsigned() Set the INTEGER column as UNSIGNED (MySQL)
     * @method $this useCurrent() Set the TIMESTAMP column to use CURRENT_TIMESTAMP as default value
     * @method $this useCurrentOnUpdate() Set the TIMESTAMP column to use CURRENT_TIMESTAMP when updating (MySQL)
     * @method $this virtualAs(string $expression) Create a virtual generated column (MySQL/PostgreSQL/SQLite)
     */
    class ColumnDefinition extends Fluent
    {
        //
    }

}


namespace Illuminate\Routing {
    abstract class Controller
    {
        /**
         * The middleware registered on the controller.
         *
         * @var array
         */
        protected $middleware = [];

        /**
         * Register middleware on the controller.
         *
         * @param \Closure|array|string $middleware
         * @param array $options
         * @return \Illuminate\Routing\ControllerMiddlewareOptions
         */
        public function middleware($middleware, array $options = [])
        {
            foreach ((array)$middleware as $m) {
                $this->middleware[] = [
                    'middleware' => $m,
                    'options' => &$options,
                ];
            }

            return new ControllerMiddlewareOptions($options);
        }

        /**
         * Get the middleware assigned to the controller.
         *
         * @return array
         */
        public function getMiddleware()
        {
            return $this->middleware;
        }

        /**
         * Execute an action on the controller.
         *
         * @param string $method
         * @param array $parameters
         * @return \Symfony\Component\HttpFoundation\Response
         */
        public function callAction($method, $parameters)
        {
            return $this->{$method}(...array_values($parameters));
        }

        /**
         * Handle calls to missing methods on the controller.
         *
         * @param string $method
         * @param array $parameters
         * @return mixed
         *
         * @throws \BadMethodCallException
         */
        public function __call($method, $parameters)
        {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }
    }
}

namespace Illuminate\Support {
    class Fluent implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
    {
        /**
         * All of the attributes set on the fluent instance.
         *
         * @var array
         */
        protected $attributes = [];

        /**
         * Create a new fluent instance.
         *
         * @param array|object $attributes
         * @return void
         */
        public function __construct($attributes = [])
        {
            foreach ($attributes as $key => $value) {
                $this->attributes[$key] = $value;
            }
        }

        /**
         * Get an attribute from the fluent instance.
         *
         * @param string $key
         * @param mixed $default
         * @return mixed
         */
        public function get($key, $default = null)
        {
            if (array_key_exists($key, $this->attributes)) {
                return $this->attributes[$key];
            }

            return value($default);
        }

        /**
         * Get the attributes from the fluent instance.
         *
         * @return array
         */
        public function getAttributes()
        {
            return $this->attributes;
        }

        /**
         * Convert the fluent instance to an array.
         *
         * @return array
         */
        public function toArray()
        {
            return $this->attributes;
        }

        /**
         * Convert the object into something JSON serializable.
         *
         * @return array
         */
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->toArray();
        }

        /**
         * Convert the fluent instance to JSON.
         *
         * @param int $options
         * @return string
         */
        public function toJson($options = 0)
        {
            return json_encode($this->jsonSerialize(), $options);
        }

        /**
         * Determine if the given offset exists.
         *
         * @param string $offset
         * @return bool
         */
        #[\ReturnTypeWillChange]
        public function offsetExists($offset)
        {
            return isset($this->attributes[$offset]);
        }

        /**
         * Get the value for a given offset.
         *
         * @param string $offset
         * @return mixed
         */
        #[\ReturnTypeWillChange]
        public function offsetGet($offset)
        {
            return $this->get($offset);
        }

        /**
         * Set the value at the given offset.
         *
         * @param string $offset
         * @param mixed $value
         * @return void
         */
        #[\ReturnTypeWillChange]
        public function offsetSet($offset, $value)
        {
            $this->attributes[$offset] = $value;
        }

        /**
         * Unset the value at the given offset.
         *
         * @param string $offset
         * @return void
         */
        #[\ReturnTypeWillChange]
        public function offsetUnset($offset)
        {
            unset($this->attributes[$offset]);
        }

        /**
         * Handle dynamic calls to the fluent instance to set attributes.
         *
         * @param string $method
         * @param array $parameters
         * @return $this
         */
        public function __call($method, $parameters)
        {
            $this->attributes[$method] = count($parameters) > 0 ? $parameters[0] : true;

            return $this;
        }

        /**
         * Dynamically retrieve the value of an attribute.
         *
         * @param string $key
         * @return mixed
         */
        public function __get($key)
        {
            return $this->get($key);
        }

        /**
         * Dynamically set the value of an attribute.
         *
         * @param string $key
         * @param mixed $value
         * @return void
         */
        public function __set($key, $value)
        {
            $this->offsetSet($key, $value);
        }

        /**
         * Dynamically check if an attribute is set.
         *
         * @param string $key
         * @return bool
         */
        public function __isset($key)
        {
            return $this->offsetExists($key);
        }

        /**
         * Dynamically unset an attribute.
         *
         * @param string $key
         * @return void
         */
        public function __unset($key)
        {
            $this->offsetUnset($key);
        }
    }

}

namespace Illuminate\Database\Eloquent {

    use Illuminate\Support\Arr;
    use Illuminate\Support\Str;

    abstract class Model implements Arrayable, ArrayAccess, HasBroadcastChannel, Jsonable, JsonSerializable, QueueableEntity, UrlRoutable
    {
        use Concerns\HasAttributes,
            Concerns\HasEvents,
            Concerns\HasGlobalScopes,
            Concerns\HasRelationships,
            Concerns\HasTimestamps,
            Concerns\HidesAttributes,
            Concerns\GuardsAttributes,
            ForwardsCalls;

        /**
         * The connection name for the model.
         *
         * @var string|null
         */
        protected $connection;

        /**
         * The table associated with the model.
         *
         * @var string
         */
        protected string $table;

        /**
         * The primary key for the model.
         *
         * @var string
         */
        protected $primaryKey = 'id';

        /**
         * The "type" of the primary key ID.
         *
         * @var string
         */
        protected $keyType = 'int';

        /**
         * Indicates if the IDs are auto-incrementing.
         *
         * @var bool
         */
        public $incrementing = true;

        /**
         * The relations to eager load on every query.
         *
         * @var array
         */
        protected $with = [];

        /**
         * The relationship counts that should be eager loaded on every query.
         *
         * @var array
         */
        protected $withCount = [];

        /**
         * Indicates whether lazy loading will be prevented on this model.
         *
         * @var bool
         */
        public $preventsLazyLoading = false;

        /**
         * The number of models to return for pagination.
         *
         * @var int
         */
        protected $perPage = 15;

        /**
         * Indicates if the model exists.
         *
         * @var bool
         */
        public $exists = false;

        /**
         * Indicates if the model was inserted during the current request lifecycle.
         *
         * @var bool
         */
        public $wasRecentlyCreated = false;

        /**
         * The connection resolver instance.
         *
         * @var \Illuminate\Database\ConnectionResolverInterface
         */
        protected static $resolver;

        /**
         * The event dispatcher instance.
         *
         * @var \Illuminate\Contracts\Events\Dispatcher
         */
        protected static $dispatcher;

        /**
         * The array of booted models.
         *
         * @var array
         */
        protected static $booted = [];

        /**
         * The array of trait initializers that will be called on each new instance.
         *
         * @var array
         */
        protected static $traitInitializers = [];

        /**
         * The array of global scopes on the model.
         *
         * @var array
         */
        protected static $globalScopes = [];

        /**
         * The list of models classes that should not be affected with touch.
         *
         * @var array
         */
        protected static $ignoreOnTouch = [];

        /**
         * Indicates whether lazy loading should be restricted on all models.
         *
         * @var bool
         */
        protected static $modelsShouldPreventLazyLoading = false;

        /**
         * The callback that is responsible for handling lazy loading violations.
         *
         * @var callable|null
         */
        protected static $lazyLoadingViolationCallback;

        /**
         * Indicates if broadcasting is currently enabled.
         *
         * @var bool
         */
        protected static $isBroadcasting = true;

        /**
         * The name of the "created at" column.
         *
         * @var string|null
         */
        const CREATED_AT = 'created_at';

        /**
         * The name of the "updated at" column.
         *
         * @var string|null
         */
        const UPDATED_AT = 'updated_at';

        /**
         * Create a new Eloquent model instance.
         *
         * @param array $attributes
         * @return void
         */
        public function __construct(array $attributes = [])
        {
            $this->bootIfNotBooted();

            $this->initializeTraits();

            $this->syncOriginal();

            $this->fill($attributes);
        }

        /**
         * Check if the model needs to be booted and if so, do it.
         *
         * @return void
         */
        protected function bootIfNotBooted()
        {
            if (!isset(static::$booted[static::class])) {
                static::$booted[static::class] = true;

                $this->fireModelEvent('booting', false);

                static::booting();
                static::boot();
                static::booted();

                $this->fireModelEvent('booted', false);
            }
        }

        /**
         * Perform any actions required before the model boots.
         *
         * @return void
         */
        protected static function booting()
        {
            //
        }

        /**
         * Bootstrap the model and its traits.
         *
         * @return void
         */
        protected static function boot()
        {
            static::bootTraits();
        }

        /**
         * Boot all of the bootable traits on the model.
         *
         * @return void
         */
        protected static function bootTraits()
        {
            $class = static::class;

            $booted = [];

            static::$traitInitializers[$class] = [];

            foreach (class_uses_recursive($class) as $trait) {
                $method = 'boot' . class_basename($trait);

                if (method_exists($class, $method) && !in_array($method, $booted)) {
                    forward_static_call([$class, $method]);

                    $booted[] = $method;
                }

                if (method_exists($class, $method = 'initialize' . class_basename($trait))) {
                    static::$traitInitializers[$class][] = $method;

                    static::$traitInitializers[$class] = array_unique(
                        static::$traitInitializers[$class]
                    );
                }
            }
        }

        /**
         * Initialize any initializable traits on the model.
         *
         * @return void
         */
        protected function initializeTraits()
        {
            foreach (static::$traitInitializers[static::class] as $method) {
                $this->{$method}();
            }
        }

        /**
         * Perform any actions required after the model boots.
         *
         * @return void
         */
        protected static function booted()
        {
            //
        }

        /**
         * Clear the list of booted models so they will be re-booted.
         *
         * @return void
         */
        public static function clearBootedModels()
        {
            static::$booted = [];

            static::$globalScopes = [];
        }

        /**
         * Disables relationship model touching for the current class during given callback scope.
         *
         * @param callable $callback
         * @return void
         */
        public static function withoutTouching(callable $callback)
        {
            static::withoutTouchingOn([static::class], $callback);
        }

        /**
         * Disables relationship model touching for the given model classes during given callback scope.
         *
         * @param array $models
         * @param callable $callback
         * @return void
         */
        public static function withoutTouchingOn(array $models, callable $callback)
        {
            static::$ignoreOnTouch = array_values(array_merge(static::$ignoreOnTouch, $models));

            try {
                $callback();
            } finally {
                static::$ignoreOnTouch = array_values(array_diff(static::$ignoreOnTouch, $models));
            }
        }

        /**
         * Determine if the given model is ignoring touches.
         *
         * @param string|null $class
         * @return bool
         */
        public static function isIgnoringTouch($class = null)
        {
            $class = $class ?: static::class;

            if (!get_class_vars($class)['timestamps'] || !$class::UPDATED_AT) {
                return true;
            }

            foreach (static::$ignoreOnTouch as $ignoredClass) {
                if ($class === $ignoredClass || is_subclass_of($class, $ignoredClass)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Prevent model relationships from being lazy loaded.
         *
         * @param bool $value
         * @return void
         */
        public static function preventLazyLoading($value = true)
        {
            static::$modelsShouldPreventLazyLoading = $value;
        }

        /**
         * Register a callback that is responsible for handling lazy loading violations.
         *
         * @param callable $callback
         * @return void
         */
        public static function handleLazyLoadingViolationUsing(callable $callback)
        {
            static::$lazyLoadingViolationCallback = $callback;
        }

        /**
         * Execute a callback without broadcasting any model events for all model types.
         *
         * @param callable $callback
         * @return mixed
         */
        public static function withoutBroadcasting(callable $callback)
        {
            $isBroadcasting = static::$isBroadcasting;

            static::$isBroadcasting = false;

            try {
                return $callback();
            } finally {
                static::$isBroadcasting = $isBroadcasting;
            }
        }

        /**
         * Fill the model with an array of attributes.
         *
         * @param array $attributes
         * @return \Illuminate\Database\Eloquent\Model
         *
         * @throws \Illuminate\Database\Eloquent\MassAssignmentException
         */
        public function fill(array $attributes)
        {
            $totallyGuarded = $this->totallyGuarded();

            foreach ($this->fillableFromArray($attributes) as $key => $value) {
                // The developers may choose to place some attributes in the "fillable" array
                // which means only those attributes may be set through mass assignment to
                // the model, and all others will just get ignored for security reasons.
                if ($this->isFillable($key)) {
                    $this->setAttribute($key, $value);
                } elseif ($totallyGuarded) {
                    throw new MassAssignmentException(sprintf(
                        'Add [%s] to fillable property to allow mass assignment on [%s].',
                        $key, get_class($this)
                    ));
                }
            }

            return $this;
        }

        /**
         * Fill the model with an array of attributes. Force mass assignment.
         *
         * @param array $attributes
         * @return $this
         */
        public function forceFill(array $attributes)
        {
            return static::unguarded(function () use ($attributes) {
                return $this->fill($attributes);
            });
        }

        /**
         * Qualify the given column name by the model's table.
         *
         * @param string $column
         * @return string
         */
        public function qualifyColumn($column)
        {
            if (Str::contains($column, '.')) {
                return $column;
            }

            return $this->getTable() . '.' . $column;
        }

        /**
         * Qualify the given columns with the model's table.
         *
         * @param array $columns
         * @return array
         */
        public function qualifyColumns($columns)
        {
            return collect($columns)->map(function ($column) {
                return $this->qualifyColumn($column);
            })->all();
        }

        /**
         * Create a new instance of the given model.
         *
         * @param array $attributes
         * @param bool $exists
         * @return static
         */
        public function newInstance($attributes = [], $exists = false)
        {
            // This method just provides a convenient way for us to generate fresh model
            // instances of this current model. It is particularly useful during the
            // hydration of new objects via the Eloquent query builder instances.
            $model = new static((array)$attributes);

            $model->exists = $exists;

            $model->setConnection(
                $this->getConnectionName()
            );

            $model->setTable($this->getTable());

            $model->mergeCasts($this->casts);

            return $model;
        }

        /**
         * Create a new model instance that is existing.
         *
         * @param array $attributes
         * @param string|null $connection
         * @return static
         */
        public function newFromBuilder($attributes = [], $connection = null)
        {
            $model = $this->newInstance([], true);

            $model->setRawAttributes((array)$attributes, true);

            $model->setConnection($connection ?: $this->getConnectionName());

            $model->fireModelEvent('retrieved', false);

            return $model;
        }

        /**
         * Begin querying the model on a given connection.
         *
         * @param string|null $connection
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public static function on($connection = null)
        {
            // First we will just create a fresh instance of this model, and then we can set the
            // connection on the model so that it is used for the queries we execute, as well
            // as being set on every relation we retrieve without a custom connection name.
            $instance = new static;

            $instance->setConnection($connection);

            return $instance->newQuery();
        }

        /**
         * Begin querying the model on the write connection.
         *
         * @return \Illuminate\Database\Query\Builder
         */
        public static function onWriteConnection()
        {
            return static::query()->useWritePdo();
        }

        /**
         * Get all of the models from the database.
         *
         * @param array|mixed $columns
         * @return \Illuminate\Database\Eloquent\Collection|static[]
         */
        public static function all($columns = ['*'])
        {
            return static::query()->get(
                is_array($columns) ? $columns : func_get_args()
            );
        }

        /**
         * Begin querying a model with eager loading.
         *
         * @param array|string $relations
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public static function with($relations)
        {
            return static::query()->with(
                is_string($relations) ? func_get_args() : $relations
            );
        }

        /**
         * Eager load relations on the model.
         *
         * @param array|string $relations
         * @return $this
         */
        public function load($relations)
        {
            $query = $this->newQueryWithoutRelationships()->with(
                is_string($relations) ? func_get_args() : $relations
            );

            $query->eagerLoadRelations([$this]);

            return $this;
        }

        /**
         * Eager load relationships on the polymorphic relation of a model.
         *
         * @param string $relation
         * @param array $relations
         * @return $this
         */
        public function loadMorph($relation, $relations)
        {
            if (!$this->{$relation}) {
                return $this;
            }

            $className = get_class($this->{$relation});

            $this->{$relation}->load($relations[$className] ?? []);

            return $this;
        }

        /**
         * Eager load relations on the model if they are not already eager loaded.
         *
         * @param array|string $relations
         * @return $this
         */
        public function loadMissing($relations)
        {
            $relations = is_string($relations) ? func_get_args() : $relations;

            $this->newCollection([$this])->loadMissing($relations);

            return $this;
        }

        /**
         * Eager load relation's column aggregations on the model.
         *
         * @param array|string $relations
         * @param string $column
         * @param string $function
         * @return $this
         */
        public function loadAggregate($relations, $column, $function = null)
        {
            $this->newCollection([$this])->loadAggregate($relations, $column, $function);

            return $this;
        }

        /**
         * Eager load relation counts on the model.
         *
         * @param array|string $relations
         * @return $this
         */
        public function loadCount($relations)
        {
            $relations = is_string($relations) ? func_get_args() : $relations;

            return $this->loadAggregate($relations, '*', 'count');
        }

        /**
         * Eager load relation max column values on the model.
         *
         * @param array|string $relations
         * @param string $column
         * @return $this
         */
        public function loadMax($relations, $column)
        {
            return $this->loadAggregate($relations, $column, 'max');
        }

        /**
         * Eager load relation min column values on the model.
         *
         * @param array|string $relations
         * @param string $column
         * @return $this
         */
        public function loadMin($relations, $column)
        {
            return $this->loadAggregate($relations, $column, 'min');
        }

        /**
         * Eager load relation's column summations on the model.
         *
         * @param array|string $relations
         * @param string $column
         * @return $this
         */
        public function loadSum($relations, $column)
        {
            return $this->loadAggregate($relations, $column, 'sum');
        }

        /**
         * Eager load relation average column values on the model.
         *
         * @param array|string $relations
         * @param string $column
         * @return $this
         */
        public function loadAvg($relations, $column)
        {
            return $this->loadAggregate($relations, $column, 'avg');
        }

        /**
         * Eager load related model existence values on the model.
         *
         * @param array|string $relations
         * @return $this
         */
        public function loadExists($relations)
        {
            return $this->loadAggregate($relations, '*', 'exists');
        }

        /**
         * Eager load relationship column aggregation on the polymorphic relation of a model.
         *
         * @param string $relation
         * @param array $relations
         * @param string $column
         * @param string $function
         * @return $this
         */
        public function loadMorphAggregate($relation, $relations, $column, $function = null)
        {
            if (!$this->{$relation}) {
                return $this;
            }

            $className = get_class($this->{$relation});

            $this->{$relation}->loadAggregate($relations[$className] ?? [], $column, $function);

            return $this;
        }

        /**
         * Eager load relationship counts on the polymorphic relation of a model.
         *
         * @param string $relation
         * @param array $relations
         * @return $this
         */
        public function loadMorphCount($relation, $relations)
        {
            return $this->loadMorphAggregate($relation, $relations, '*', 'count');
        }

        /**
         * Eager load relationship max column values on the polymorphic relation of a model.
         *
         * @param string $relation
         * @param array $relations
         * @param string $column
         * @return $this
         */
        public function loadMorphMax($relation, $relations, $column)
        {
            return $this->loadMorphAggregate($relation, $relations, $column, 'max');
        }

        /**
         * Eager load relationship min column values on the polymorphic relation of a model.
         *
         * @param string $relation
         * @param array $relations
         * @param string $column
         * @return $this
         */
        public function loadMorphMin($relation, $relations, $column)
        {
            return $this->loadMorphAggregate($relation, $relations, $column, 'min');
        }

        /**
         * Eager load relationship column summations on the polymorphic relation of a model.
         *
         * @param string $relation
         * @param array $relations
         * @param string $column
         * @return $this
         */
        public function loadMorphSum($relation, $relations, $column)
        {
            return $this->loadMorphAggregate($relation, $relations, $column, 'sum');
        }

        /**
         * Eager load relationship average column values on the polymorphic relation of a model.
         *
         * @param string $relation
         * @param array $relations
         * @param string $column
         * @return $this
         */
        public function loadMorphAvg($relation, $relations, $column)
        {
            return $this->loadMorphAggregate($relation, $relations, $column, 'avg');
        }

        /**
         * Increment a column's value by a given amount.
         *
         * @param string $column
         * @param float|int $amount
         * @param array $extra
         * @return int
         */
        protected function increment($column, $amount = 1, array $extra = [])
        {
            return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
        }

        /**
         * Decrement a column's value by a given amount.
         *
         * @param string $column
         * @param float|int $amount
         * @param array $extra
         * @return int
         */
        protected function decrement($column, $amount = 1, array $extra = [])
        {
            return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
        }

        /**
         * Run the increment or decrement method on the model.
         *
         * @param string $column
         * @param float|int $amount
         * @param array $extra
         * @param string $method
         * @return int
         */
        protected function incrementOrDecrement($column, $amount, $extra, $method)
        {
            $query = $this->newQueryWithoutRelationships();

            if (!$this->exists) {
                return $query->{$method}($column, $amount, $extra);
            }

            $this->{$column} = $this->isClassDeviable($column)
                ? $this->deviateClassCastableAttribute($method, $column, $amount)
                : $this->{$column} + ($method === 'increment' ? $amount : $amount * -1);

            $this->forceFill($extra);

            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            return tap($this->setKeysForSaveQuery($query)->{$method}($column, $amount, $extra), function () use ($column) {
                $this->syncChanges();

                $this->fireModelEvent('updated', false);

                $this->syncOriginalAttribute($column);
            });
        }

        /**
         * Update the model in the database.
         *
         * @param array $attributes
         * @param array $options
         * @return bool
         */
        public function update(array $attributes = [], array $options = [])
        {
            if (!$this->exists) {
                return false;
            }

            return $this->fill($attributes)->save($options);
        }

        /**
         * Update the model in the database within a transaction.
         *
         * @param array $attributes
         * @param array $options
         * @return bool
         *
         * @throws \Throwable
         */
        public function updateOrFail(array $attributes = [], array $options = [])
        {
            if (!$this->exists) {
                return false;
            }

            return $this->fill($attributes)->saveOrFail($options);
        }

        /**
         * Update the model in the database without raising any events.
         *
         * @param array $attributes
         * @param array $options
         * @return bool
         */
        public function updateQuietly(array $attributes = [], array $options = [])
        {
            if (!$this->exists) {
                return false;
            }

            return $this->fill($attributes)->saveQuietly($options);
        }

        /**
         * Save the model and all of its relationships.
         *
         * @return bool
         */
        public function push()
        {
            if (!$this->save()) {
                return false;
            }

            // To sync all of the relationships to the database, we will simply spin through
            // the relationships and save each model via this "push" method, which allows
            // us to recurse into all of these nested relations for the model instance.
            foreach ($this->relations as $models) {
                $models = $models instanceof Collection
                    ? $models->all() : [$models];

                foreach (array_filter($models) as $model) {
                    if (!$model->push()) {
                        return false;
                    }
                }
            }

            return true;
        }

        /**
         * Save the model to the database without raising any events.
         *
         * @param array $options
         * @return bool
         */
        public function saveQuietly(array $options = [])
        {
            return static::withoutEvents(function () use ($options) {
                return $this->save($options);
            });
        }

        /**
         * Save the model to the database.
         *
         * @param array $options
         * @return bool
         */
        public function save(array $options = [])
        {
            $this->mergeAttributesFromClassCasts();

            $query = $this->newModelQuery();

            // If the "saving" event returns false we'll bail out of the save and return
            // false, indicating that the save failed. This provides a chance for any
            // listeners to cancel save operations if validations fail or whatever.
            if ($this->fireModelEvent('saving') === false) {
                return false;
            }

            // If the model already exists in the database we can just update our record
            // that is already in this database using the current IDs in this "where"
            // clause to only update this model. Otherwise, we'll just insert them.
            if ($this->exists) {
                $saved = $this->isDirty() ?
                    $this->performUpdate($query) : true;
            }

            // If the model is brand new, we'll insert it into our database and set the
            // ID attribute on the model to the value of the newly inserted row's ID
            // which is typically an auto-increment value managed by the database.
            else {
                $saved = $this->performInsert($query);

                if (!$this->getConnectionName() &&
                    $connection = $query->getConnection()) {
                    $this->setConnection($connection->getName());
                }
            }

            // If the model is successfully saved, we need to do a few more things once
            // that is done. We will call the "saved" method here to run any actions
            // we need to happen after a model gets successfully saved right here.
            if ($saved) {
                $this->finishSave($options);
            }

            return $saved;
        }

        /**
         * Save the model to the database within a transaction.
         *
         * @param array $options
         * @return bool
         *
         * @throws \Throwable
         */
        public function saveOrFail(array $options = [])
        {
            return $this->getConnection()->transaction(function () use ($options) {
                return $this->save($options);
            });
        }

        /**
         * Perform any actions that are necessary after the model is saved.
         *
         * @param array $options
         * @return void
         */
        protected function finishSave(array $options)
        {
            $this->fireModelEvent('saved', false);

            if ($this->isDirty() && ($options['touch'] ?? true)) {
                $this->touchOwners();
            }

            $this->syncOriginal();
        }

        /**
         * Perform a model update operation.
         *
         * @param \Illuminate\Database\Eloquent\Builder $query
         * @return bool
         */
        protected function performUpdate(Builder $query)
        {
            // If the updating event returns false, we will cancel the update operation so
            // developers can hook Validation systems into their models and cancel this
            // operation if the model does not pass validation. Otherwise, we update.
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            // First we need to create a fresh query instance and touch the creation and
            // update timestamp on the model which are maintained by us for developer
            // convenience. Then we will just continue saving the model instances.
            if ($this->usesTimestamps()) {
                $this->updateTimestamps();
            }

            // Once we have run the update operation, we will fire the "updated" event for
            // this model instance. This will allow developers to hook into these after
            // models are updated, giving them a chance to do any special processing.
            $dirty = $this->getDirty();

            if (count($dirty) > 0) {
                $this->setKeysForSaveQuery($query)->update($dirty);

                $this->syncChanges();

                $this->fireModelEvent('updated', false);
            }

            return true;
        }

        /**
         * Set the keys for a select query.
         *
         * @param \Illuminate\Database\Eloquent\Builder $query
         * @return \Illuminate\Database\Eloquent\Builder
         */
        protected function setKeysForSelectQuery($query)
        {
            $query->where($this->getKeyName(), '=', $this->getKeyForSelectQuery());

            return $query;
        }

        /**
         * Get the primary key value for a select query.
         *
         * @return mixed
         */
        protected function getKeyForSelectQuery()
        {
            return $this->original[$this->getKeyName()] ?? $this->getKey();
        }

        /**
         * Set the keys for a save update query.
         *
         * @param \Illuminate\Database\Eloquent\Builder $query
         * @return \Illuminate\Database\Eloquent\Builder
         */
        protected function setKeysForSaveQuery($query)
        {
            $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

            return $query;
        }

        /**
         * Get the primary key value for a save query.
         *
         * @return mixed
         */
        protected function getKeyForSaveQuery()
        {
            return $this->original[$this->getKeyName()] ?? $this->getKey();
        }

        /**
         * Perform a model insert operation.
         *
         * @param \Illuminate\Database\Eloquent\Builder $query
         * @return bool
         */
        protected function performInsert(Builder $query)
        {
            if ($this->fireModelEvent('creating') === false) {
                return false;
            }

            // First we'll need to create a fresh query instance and touch the creation and
            // update timestamps on this model, which are maintained by us for developer
            // convenience. After, we will just continue saving these model instances.
            if ($this->usesTimestamps()) {
                $this->updateTimestamps();
            }

            // If the model has an incrementing key, we can use the "insertGetId" method on
            // the query builder, which will give us back the final inserted ID for this
            // table from the database. Not all tables have to be incrementing though.
            $attributes = $this->getAttributesForInsert();

            if ($this->getIncrementing()) {
                $this->insertAndSetId($query, $attributes);
            }

            // If the table isn't incrementing we'll simply insert these attributes as they
            // are. These attribute arrays must contain an "id" column previously placed
            // there by the developer as the manually determined key for these models.
            else {
                if (empty($attributes)) {
                    return true;
                }

                $query->insert($attributes);
            }

            // We will go ahead and set the exists property to true, so that it is set when
            // the created event is fired, just in case the developer tries to update it
            // during the event. This will allow them to do so and run an update here.
            $this->exists = true;

            $this->wasRecentlyCreated = true;

            $this->fireModelEvent('created', false);

            return true;
        }

        /**
         * Insert the given attributes and set the ID on the model.
         *
         * @param \Illuminate\Database\Eloquent\Builder $query
         * @param array $attributes
         * @return void
         */
        protected function insertAndSetId(Builder $query, $attributes)
        {
            $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());

            $this->setAttribute($keyName, $id);
        }

        /**
         * Destroy the models for the given IDs.
         *
         * @param \Illuminate\Support\Collection|array|int|string $ids
         * @return int
         */
        public static function destroy($ids)
        {
            if ($ids instanceof EloquentCollection) {
                $ids = $ids->modelKeys();
            }

            if ($ids instanceof BaseCollection) {
                $ids = $ids->all();
            }

            $ids = is_array($ids) ? $ids : func_get_args();

            if (count($ids) === 0) {
                return 0;
            }

            // We will actually pull the models from the database table and call delete on
            // each of them individually so that their events get fired properly with a
            // correct set of attributes in case the developers wants to check these.
            $key = ($instance = new static)->getKeyName();

            $count = 0;

            foreach ($instance->whereIn($key, $ids)->get() as $model) {
                if ($model->delete()) {
                    $count++;
                }
            }

            return $count;
        }

        /**
         * Delete the model from the database.
         *
         * @return bool|null
         *
         * @throws \LogicException
         */
        public function delete()
        {
            $this->mergeAttributesFromClassCasts();

            if (is_null($this->getKeyName())) {
                throw new LogicException('No primary key defined on model.');
            }

            // If the model doesn't exist, there is nothing to delete so we'll just return
            // immediately and not do anything else. Otherwise, we will continue with a
            // deletion process on the model, firing the proper events, and so forth.
            if (!$this->exists) {
                return;
            }

            if ($this->fireModelEvent('deleting') === false) {
                return false;
            }

            // Here, we'll touch the owning models, verifying these timestamps get updated
            // for the models. This will allow any caching to get broken on the parents
            // by the timestamp. Then we will go ahead and delete the model instance.
            $this->touchOwners();

            $this->performDeleteOnModel();

            // Once the model has been deleted, we will fire off the deleted event so that
            // the developers may hook into post-delete operations. We will then return
            // a boolean true as the delete is presumably successful on the database.
            $this->fireModelEvent('deleted', false);

            return true;
        }

        /**
         * Delete the model from the database within a transaction.
         *
         * @return bool|null
         *
         * @throws \Throwable
         */
        public function deleteOrFail()
        {
            if (!$this->exists) {
                return false;
            }

            return $this->getConnection()->transaction(function () {
                return $this->delete();
            });
        }

        /**
         * Force a hard delete on a soft deleted model.
         *
         * This method protects developers from running forceDelete when the trait is missing.
         *
         * @return bool|null
         */
        public function forceDelete()
        {
            return $this->delete();
        }

        /**
         * Perform the actual delete query on this model instance.
         *
         * @return void
         */
        protected function performDeleteOnModel()
        {
            $this->setKeysForSaveQuery($this->newModelQuery())->delete();

            $this->exists = false;
        }

        /**
         * Begin querying the model.
         *
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public static function query()
        {
            return (new static)->newQuery();
        }

        /**
         * Get a new query builder for the model's table.
         *
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public function newQuery()
        {
            return $this->registerGlobalScopes($this->newQueryWithoutScopes());
        }

        /**
         * Get a new query builder that doesn't have any global scopes or eager loading.
         *
         * @return \Illuminate\Database\Eloquent\Builder|static
         */
        public function newModelQuery()
        {
            return $this->newEloquentBuilder(
                $this->newBaseQueryBuilder()
            )->setModel($this);
        }

        /**
         * Get a new query builder with no relationships loaded.
         *
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public function newQueryWithoutRelationships()
        {
            return $this->registerGlobalScopes($this->newModelQuery());
        }

        /**
         * Register the global scopes for this builder instance.
         *
         * @param \Illuminate\Database\Eloquent\Builder $builder
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public function registerGlobalScopes($builder)
        {
            foreach ($this->getGlobalScopes() as $identifier => $scope) {
                $builder->withGlobalScope($identifier, $scope);
            }

            return $builder;
        }

        /**
         * Get a new query builder that doesn't have any global scopes.
         *
         * @return \Illuminate\Database\Eloquent\Builder|static
         */
        public function newQueryWithoutScopes()
        {
            return $this->newModelQuery()
                ->with($this->with)
                ->withCount($this->withCount);
        }

        /**
         * Get a new query instance without a given scope.
         *
         * @param \Illuminate\Database\Eloquent\Scope|string $scope
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public function newQueryWithoutScope($scope)
        {
            return $this->newQuery()->withoutGlobalScope($scope);
        }

        /**
         * Get a new query to restore one or more models by their queueable IDs.
         *
         * @param array|int $ids
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public function newQueryForRestoration($ids)
        {
            return is_array($ids)
                ? $this->newQueryWithoutScopes()->whereIn($this->getQualifiedKeyName(), $ids)
                : $this->newQueryWithoutScopes()->whereKey($ids);
        }

        /**
         * Create a new Eloquent query builder for the model.
         *
         * @param \Illuminate\Database\Query\Builder $query
         * @return \Illuminate\Database\Eloquent\Builder|static
         */
        public function newEloquentBuilder($query)
        {
            return new Builder($query);
        }

        /**
         * Get a new query builder instance for the connection.
         *
         * @return \Illuminate\Database\Query\Builder
         */
        protected function newBaseQueryBuilder()
        {
            return $this->getConnection()->query();
        }

        /**
         * Create a new Eloquent Collection instance.
         *
         * @param array $models
         * @return \Illuminate\Database\Eloquent\Collection
         */
        public function newCollection(array $models = [])
        {
            return new Collection($models);
        }

        /**
         * Create a new pivot model instance.
         *
         * @param \Illuminate\Database\Eloquent\Model $parent
         * @param array $attributes
         * @param string $table
         * @param bool $exists
         * @param string|null $using
         * @return \Illuminate\Database\Eloquent\Relations\Pivot
         */
        public function newPivot(self $parent, array $attributes, $table, $exists, $using = null)
        {
            return $using ? $using::fromRawAttributes($parent, $attributes, $table, $exists)
                : Pivot::fromAttributes($parent, $attributes, $table, $exists);
        }

        /**
         * Determine if the model has a given scope.
         *
         * @param string $scope
         * @return bool
         */
        public function hasNamedScope($scope)
        {
            return method_exists($this, 'scope' . ucfirst($scope));
        }

        /**
         * Apply the given named scope if possible.
         *
         * @param string $scope
         * @param array $parameters
         * @return mixed
         */
        public function callNamedScope($scope, array $parameters = [])
        {
            return $this->{'scope' . ucfirst($scope)}(...$parameters);
        }

        /**
         * Convert the model instance to an array.
         *
         * @return array
         */
        public function toArray()
        {
            return array_merge($this->attributesToArray(), $this->relationsToArray());
        }

        /**
         * Convert the model instance to JSON.
         *
         * @param int $options
         * @return string
         *
         * @throws \Illuminate\Database\Eloquent\JsonEncodingException
         */
        public function toJson($options = 0)
        {
            $json = json_encode($this->jsonSerialize(), $options);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw JsonEncodingException::forModel($this, json_last_error_msg());
            }

            return $json;
        }

        /**
         * Convert the object into something JSON serializable.
         *
         * @return array
         */
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->toArray();
        }

        /**
         * Reload a fresh model instance from the database.
         *
         * @param array|string $with
         * @return static|null
         */
        public function fresh($with = [])
        {
            if (!$this->exists) {
                return;
            }

            return $this->setKeysForSelectQuery($this->newQueryWithoutScopes())
                ->with(is_string($with) ? func_get_args() : $with)
                ->first();
        }

        /**
         * Reload the current model instance with fresh attributes from the database.
         *
         * @return $this
         */
        public function refresh()
        {
            if (!$this->exists) {
                return $this;
            }

            $this->setRawAttributes(
                $this->setKeysForSelectQuery($this->newQueryWithoutScopes())->firstOrFail()->attributes
            );

            $this->load(collect($this->relations)->reject(function ($relation) {
                return $relation instanceof Pivot
                    || (is_object($relation) && in_array(AsPivot::class, class_uses_recursive($relation), true));
            })->keys()->all());

            $this->syncOriginal();

            return $this;
        }

        /**
         * Clone the model into a new, non-existing instance.
         *
         * @param array|null $except
         * @return static
         */
        public function replicate(array $except = null)
        {
            $defaults = [
                $this->getKeyName(),
                $this->getCreatedAtColumn(),
                $this->getUpdatedAtColumn(),
            ];

            $attributes = Arr::except(
                $this->getAttributes(), $except ? array_unique(array_merge($except, $defaults)) : $defaults
            );

            return tap(new static, function ($instance) use ($attributes) {
                $instance->setRawAttributes($attributes);

                $instance->setRelations($this->relations);

                $instance->fireModelEvent('replicating', false);
            });
        }

        /**
         * Determine if two models have the same ID and belong to the same table.
         *
         * @param \Illuminate\Database\Eloquent\Model|null $model
         * @return bool
         */
        public function is($model)
        {
            return !is_null($model) &&
                $this->getKey() === $model->getKey() &&
                $this->getTable() === $model->getTable() &&
                $this->getConnectionName() === $model->getConnectionName();
        }

        /**
         * Determine if two models are not the same.
         *
         * @param \Illuminate\Database\Eloquent\Model|null $model
         * @return bool
         */
        public function isNot($model)
        {
            return !$this->is($model);
        }

        /**
         * Get the database connection for the model.
         *
         * @return \Illuminate\Database\Connection
         */
        public function getConnection()
        {
            return static::resolveConnection($this->getConnectionName());
        }

        /**
         * Get the current connection name for the model.
         *
         * @return string|null
         */
        public function getConnectionName()
        {
            return $this->connection;
        }

        /**
         * Set the connection associated with the model.
         *
         * @param string|null $name
         * @return $this
         */
        public function setConnection($name)
        {
            $this->connection = $name;

            return $this;
        }

        /**
         * Resolve a connection instance.
         *
         * @param string|null $connection
         * @return \Illuminate\Database\Connection
         */
        public static function resolveConnection($connection = null)
        {
            return static::$resolver->connection($connection);
        }

        /**
         * Get the connection resolver instance.
         *
         * @return \Illuminate\Database\ConnectionResolverInterface
         */
        public static function getConnectionResolver()
        {
            return static::$resolver;
        }

        /**
         * Set the connection resolver instance.
         *
         * @param \Illuminate\Database\ConnectionResolverInterface $resolver
         * @return void
         */
        public static function setConnectionResolver(Resolver $resolver)
        {
            static::$resolver = $resolver;
        }

        /**
         * Unset the connection resolver for models.
         *
         * @return void
         */
        public static function unsetConnectionResolver()
        {
            static::$resolver = null;
        }

        /**
         * Get the table associated with the model.
         *
         * @return string
         */
        public function getTable()
        {
            return $this->table ?? Str::snake(Str::pluralStudly(class_basename($this)));
        }

        /**
         * Set the table associated with the model.
         *
         * @param string $table
         * @return $this
         */
        public function setTable($table)
        {
            $this->table = $table;

            return $this;
        }

        /**
         * Get the primary key for the model.
         *
         * @return string
         */
        public function getKeyName()
        {
            return $this->primaryKey;
        }

        /**
         * Set the primary key for the model.
         *
         * @param string $key
         * @return $this
         */
        public function setKeyName($key)
        {
            $this->primaryKey = $key;

            return $this;
        }

        /**
         * Get the table qualified key name.
         *
         * @return string
         */
        public function getQualifiedKeyName()
        {
            return $this->qualifyColumn($this->getKeyName());
        }

        /**
         * Get the auto-incrementing key type.
         *
         * @return string
         */
        public function getKeyType()
        {
            return $this->keyType;
        }

        /**
         * Set the data type for the primary key.
         *
         * @param string $type
         * @return $this
         */
        public function setKeyType($type)
        {
            $this->keyType = $type;

            return $this;
        }

        /**
         * Get the value indicating whether the IDs are incrementing.
         *
         * @return bool
         */
        public function getIncrementing()
        {
            return $this->incrementing;
        }

        /**
         * Set whether IDs are incrementing.
         *
         * @param bool $value
         * @return $this
         */
        public function setIncrementing($value)
        {
            $this->incrementing = $value;

            return $this;
        }

        /**
         * Get the value of the model's primary key.
         *
         * @return mixed
         */
        public function getKey()
        {
            return $this->getAttribute($this->getKeyName());
        }

        /**
         * Get the queueable identity for the entity.
         *
         * @return mixed
         */
        public function getQueueableId()
        {
            return $this->getKey();
        }

        /**
         * Get the queueable relationships for the entity.
         *
         * @return array
         */
        public function getQueueableRelations()
        {
            $relations = [];

            foreach ($this->getRelations() as $key => $relation) {
                if (!method_exists($this, $key)) {
                    continue;
                }

                $relations[] = $key;

                if ($relation instanceof QueueableCollection) {
                    foreach ($relation->getQueueableRelations() as $collectionValue) {
                        $relations[] = $key . '.' . $collectionValue;
                    }
                }

                if ($relation instanceof QueueableEntity) {
                    foreach ($relation->getQueueableRelations() as $entityKey => $entityValue) {
                        $relations[] = $key . '.' . $entityValue;
                    }
                }
            }

            return array_unique($relations);
        }

        /**
         * Get the queueable connection for the entity.
         *
         * @return string|null
         */
        public function getQueueableConnection()
        {
            return $this->getConnectionName();
        }

        /**
         * Get the value of the model's route key.
         *
         * @return mixed
         */
        public function getRouteKey()
        {
            return $this->getAttribute($this->getRouteKeyName());
        }

        /**
         * Get the route key for the model.
         *
         * @return string
         */
        public function getRouteKeyName()
        {
            return $this->getKeyName();
        }

        /**
         * Retrieve the model for a bound value.
         *
         * @param mixed $value
         * @param string|null $field
         * @return \Illuminate\Database\Eloquent\Model|null
         */
        public function resolveRouteBinding($value, $field = null)
        {
            return $this->where($field ?? $this->getRouteKeyName(), $value)->first();
        }

        /**
         * Retrieve the model for a bound value.
         *
         * @param mixed $value
         * @param string|null $field
         * @return \Illuminate\Database\Eloquent\Model|null
         */
        public function resolveSoftDeletableRouteBinding($value, $field = null)
        {
            return $this->where($field ?? $this->getRouteKeyName(), $value)->withTrashed()->first();
        }

        /**
         * Retrieve the child model for a bound value.
         *
         * @param string $childType
         * @param mixed $value
         * @param string|null $field
         * @return \Illuminate\Database\Eloquent\Model|null
         */
        public function resolveChildRouteBinding($childType, $value, $field)
        {
            return $this->resolveChildRouteBindingQuery($childType, $value, $field)->first();
        }

        /**
         * Retrieve the child model for a bound value.
         *
         * @param string $childType
         * @param mixed $value
         * @param string|null $field
         * @return \Illuminate\Database\Eloquent\Model|null
         */
        public function resolveSoftDeletableChildRouteBinding($childType, $value, $field)
        {
            return $this->resolveChildRouteBindingQuery($childType, $value, $field)->withTrashed()->first();
        }

        /**
         * Retrieve the child model query for a bound value.
         *
         * @param string $childType
         * @param mixed $value
         * @param string|null $field
         * @return \Illuminate\Database\Eloquent\Model|null
         */
        protected function resolveChildRouteBindingQuery($childType, $value, $field)
        {
            $relationship = $this->{Str::plural(Str::camel($childType))}();

            $field = $field ?: $relationship->getRelated()->getRouteKeyName();

            if ($relationship instanceof HasManyThrough ||
                $relationship instanceof BelongsToMany) {
                return $relationship->where($relationship->getRelated()->getTable() . '.' . $field, $value);
            } else {
                return $relationship->where($field, $value);
            }
        }

        /**
         * Get the default foreign key name for the model.
         *
         * @return string
         */
        public function getForeignKey()
        {
            return Str::snake(class_basename($this)) . '_' . $this->getKeyName();
        }

        /**
         * Get the number of models to return per page.
         *
         * @return int
         */
        public function getPerPage()
        {
            return $this->perPage;
        }

        /**
         * Set the number of models to return per page.
         *
         * @param int $perPage
         * @return $this
         */
        public function setPerPage($perPage)
        {
            $this->perPage = $perPage;

            return $this;
        }

        /**
         * Determine if lazy loading is disabled.
         *
         * @return bool
         */
        public static function preventsLazyLoading()
        {
            return static::$modelsShouldPreventLazyLoading;
        }

        /**
         * Get the broadcast channel route definition that is associated with the given entity.
         *
         * @return string
         */
        public function broadcastChannelRoute()
        {
            return str_replace('\\', '.', get_class($this)) . '.{' . Str::camel(class_basename($this)) . '}';
        }

        /**
         * Get the broadcast channel name that is associated with the given entity.
         *
         * @return string
         */
        public function broadcastChannel()
        {
            return str_replace('\\', '.', get_class($this)) . '.' . $this->getKey();
        }

        /**
         * Dynamically retrieve attributes on the model.
         *
         * @param string $key
         * @return mixed
         */
        public function __get($key)
        {
            return $this->getAttribute($key);
        }

        /**
         * Dynamically set attributes on the model.
         *
         * @param string $key
         * @param mixed $value
         * @return void
         */
        public function __set($key, $value)
        {
            $this->setAttribute($key, $value);
        }

        /**
         * Determine if the given attribute exists.
         *
         * @param mixed $offset
         * @return bool
         */
        #[\ReturnTypeWillChange]
        public function offsetExists($offset)
        {
            return !is_null($this->getAttribute($offset));
        }

        /**
         * Get the value for a given offset.
         *
         * @param mixed $offset
         * @return mixed
         */
        #[\ReturnTypeWillChange]
        public function offsetGet($offset)
        {
            return $this->getAttribute($offset);
        }

        /**
         * Set the value for a given offset.
         *
         * @param mixed $offset
         * @param mixed $value
         * @return void
         */
        #[\ReturnTypeWillChange]
        public function offsetSet($offset, $value)
        {
            $this->setAttribute($offset, $value);
        }

        /**
         * Unset the value for a given offset.
         *
         * @param mixed $offset
         * @return void
         */
        #[\ReturnTypeWillChange]
        public function offsetUnset($offset)
        {
            unset($this->attributes[$offset], $this->relations[$offset]);
        }

        /**
         * Determine if an attribute or relation exists on the model.
         *
         * @param string $key
         * @return bool
         */
        public function __isset($key)
        {
            return $this->offsetExists($key);
        }

        /**
         * Unset an attribute on the model.
         *
         * @param string $key
         * @return void
         */
        public function __unset($key)
        {
            $this->offsetUnset($key);
        }

        /**
         * Handle dynamic method calls into the model.
         *
         * @param string $method
         * @param array $parameters
         * @return mixed
         */
        public function __call($method, $parameters)
        {
            if (in_array($method, ['increment', 'decrement'])) {
                return $this->$method(...$parameters);
            }

            if ($resolver = (static::$relationResolvers[get_class($this)][$method] ?? null)) {
                return $resolver($this);
            }

            return $this->forwardCallTo($this->newQuery(), $method, $parameters);
        }

        /**
         * Handle dynamic static method calls into the model.
         *
         * @param string $method
         * @param array $parameters
         * @return mixed
         */
        public static function __callStatic($method, $parameters)
        {
            return (new static)->$method(...$parameters);
        }

        /**
         * Convert the model to its string representation.
         *
         * @return string
         */
        public function __toString()
        {
            return $this->toJson();
        }

        /**
         * Prepare the object for serialization.
         *
         * @return array
         */
        public function __sleep()
        {
            $this->mergeAttributesFromClassCasts();

            $this->classCastCache = [];

            return array_keys(get_object_vars($this));
        }

        /**
         * When a model is being unserialized, check if it needs to be booted.
         *
         * @return void
         */
        public function __wakeup()
        {
            $this->bootIfNotBooted();

            $this->initializeTraits();
        }
    }
}

namespace Carbon {

    use Carbon\Traits\Date;
    use DateTime;
    use DateTimeInterface;
    use DateTimeZone;

    /**
     * A simple API extension for DateTime.
     *
     * <autodoc generated by `composer phpdoc`>
     *
     * @property      int $year
     * @property      int $yearIso
     * @property      int $month
     * @property      int $day
     * @property      int $hour
     * @property      int $minute
     * @property      int $second
     * @property      int $micro
     * @property      int $microsecond
     * @property      int|float|string $timestamp                                                                           seconds since the Unix Epoch
     * @property      string $englishDayOfWeek                                                                    the day of week in English
     * @property      string $shortEnglishDayOfWeek                                                               the abbreviated day of week in English
     * @property      string $englishMonth                                                                        the month in English
     * @property      string $shortEnglishMonth                                                                   the abbreviated month in English
     * @property      string $localeDayOfWeek                                                                     the day of week in current locale LC_TIME
     * @property      string $shortLocaleDayOfWeek                                                                the abbreviated day of week in current locale LC_TIME
     * @property      string $localeMonth                                                                         the month in current locale LC_TIME
     * @property      string $shortLocaleMonth                                                                    the abbreviated month in current locale LC_TIME
     * @property      int $milliseconds
     * @property      int $millisecond
     * @property      int $milli
     * @property      int $week                                                                                1 through 53
     * @property      int $isoWeek                                                                             1 through 53
     * @property      int $weekYear                                                                            year according to week format
     * @property      int $isoWeekYear                                                                         year according to ISO week format
     * @property      int $dayOfYear                                                                           1 through 366
     * @property      int $age                                                                                 does a diffInYears() with default parameters
     * @property      int $offset                                                                              the timezone offset in seconds from UTC
     * @property      int $offsetMinutes                                                                       the timezone offset in minutes from UTC
     * @property      int $offsetHours                                                                         the timezone offset in hours from UTC
     * @property      CarbonTimeZone $timezone                                                                            the current timezone
     * @property      CarbonTimeZone $tz                                                                                  alias of $timezone
     * @property-read int $dayOfWeek                                                                           0 (for Sunday) through 6 (for Saturday)
     * @property-read int $dayOfWeekIso                                                                        1 (for Monday) through 7 (for Sunday)
     * @property-read int $weekOfYear                                                                          ISO-8601 week number of year, weeks starting on Monday
     * @property-read int $daysInMonth                                                                         number of days in the given month
     * @property-read string $latinMeridiem                                                                       "am"/"pm" (Ante meridiem or Post meridiem latin lowercase mark)
     * @property-read string $latinUpperMeridiem                                                                  "AM"/"PM" (Ante meridiem or Post meridiem latin uppercase mark)
     * @property-read string $timezoneAbbreviatedName                                                             the current timezone abbreviated name
     * @property-read string $tzAbbrName                                                                          alias of $timezoneAbbreviatedName
     * @property-read string $dayName                                                                             long name of weekday translated according to Carbon locale, in english if no translation available for current language
     * @property-read string $shortDayName                                                                        short name of weekday translated according to Carbon locale, in english if no translation available for current language
     * @property-read string $minDayName                                                                          very short name of weekday translated according to Carbon locale, in english if no translation available for current language
     * @property-read string $monthName                                                                           long name of month translated according to Carbon locale, in english if no translation available for current language
     * @property-read string $shortMonthName                                                                      short name of month translated according to Carbon locale, in english if no translation available for current language
     * @property-read string $meridiem                                                                            lowercase meridiem mark translated according to Carbon locale, in latin if no translation available for current language
     * @property-read string $upperMeridiem                                                                       uppercase meridiem mark translated according to Carbon locale, in latin if no translation available for current language
     * @property-read int $noZeroHour                                                                          current hour from 1 to 24
     * @property-read int $weeksInYear                                                                         51 through 53
     * @property-read int $isoWeeksInYear                                                                      51 through 53
     * @property-read int $weekOfMonth                                                                         1 through 5
     * @property-read int $weekNumberInMonth                                                                   1 through 5
     * @property-read int $firstWeekDay                                                                        0 through 6
     * @property-read int $lastWeekDay                                                                         0 through 6
     * @property-read int $daysInYear                                                                          365 or 366
     * @property-read int $quarter                                                                             the quarter of this instance, 1 - 4
     * @property-read int $decade                                                                              the decade of this instance
     * @property-read int $century                                                                             the century of this instance
     * @property-read int $millennium                                                                          the millennium of this instance
     * @property-read bool $dst                                                                                 daylight savings time indicator, true if DST, false otherwise
     * @property-read bool $local                                                                               checks if the timezone is local, true if local, false otherwise
     * @property-read bool $utc                                                                                 checks if the timezone is UTC, true if UTC, false otherwise
     * @property-read string $timezoneName                                                                        the current timezone name
     * @property-read string $tzName                                                                              alias of $timezoneName
     * @property-read string $locale                                                                              locale of the current instance
     *
     * @method        bool                isUtc()                                                                              Check if the current instance has UTC timezone. (Both isUtc and isUTC cases are valid.)
     * @method        bool                isLocal()                                                                            Check if the current instance has non-UTC timezone.
     * @method        bool                isValid()                                                                            Check if the current instance is a valid date.
     * @method        bool                isDST()                                                                              Check if the current instance is in a daylight saving time.
     * @method        bool                isSunday()                                                                           Checks if the instance day is sunday.
     * @method        bool                isMonday()                                                                           Checks if the instance day is monday.
     * @method        bool                isTuesday()                                                                          Checks if the instance day is tuesday.
     * @method        bool                isWednesday()                                                                        Checks if the instance day is wednesday.
     * @method        bool                isThursday()                                                                         Checks if the instance day is thursday.
     * @method        bool                isFriday()                                                                           Checks if the instance day is friday.
     * @method        bool                isSaturday()                                                                         Checks if the instance day is saturday.
     * @method        bool                isSameYear(Carbon|DateTimeInterface|string|null $date = null)                        Checks if the given date is in the same year as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentYear()                                                                      Checks if the instance is in the same year as the current moment.
     * @method        bool                isNextYear()                                                                         Checks if the instance is in the same year as the current moment next year.
     * @method        bool                isLastYear()                                                                         Checks if the instance is in the same year as the current moment last year.
     * @method        bool                isSameWeek(Carbon|DateTimeInterface|string|null $date = null)                        Checks if the given date is in the same week as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentWeek()                                                                      Checks if the instance is in the same week as the current moment.
     * @method        bool                isNextWeek()                                                                         Checks if the instance is in the same week as the current moment next week.
     * @method        bool                isLastWeek()                                                                         Checks if the instance is in the same week as the current moment last week.
     * @method        bool                isSameDay(Carbon|DateTimeInterface|string|null $date = null)                         Checks if the given date is in the same day as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentDay()                                                                       Checks if the instance is in the same day as the current moment.
     * @method        bool                isNextDay()                                                                          Checks if the instance is in the same day as the current moment next day.
     * @method        bool                isLastDay()                                                                          Checks if the instance is in the same day as the current moment last day.
     * @method        bool                isSameHour(Carbon|DateTimeInterface|string|null $date = null)                        Checks if the given date is in the same hour as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentHour()                                                                      Checks if the instance is in the same hour as the current moment.
     * @method        bool                isNextHour()                                                                         Checks if the instance is in the same hour as the current moment next hour.
     * @method        bool                isLastHour()                                                                         Checks if the instance is in the same hour as the current moment last hour.
     * @method        bool                isSameMinute(Carbon|DateTimeInterface|string|null $date = null)                      Checks if the given date is in the same minute as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentMinute()                                                                    Checks if the instance is in the same minute as the current moment.
     * @method        bool                isNextMinute()                                                                       Checks if the instance is in the same minute as the current moment next minute.
     * @method        bool                isLastMinute()                                                                       Checks if the instance is in the same minute as the current moment last minute.
     * @method        bool                isSameSecond(Carbon|DateTimeInterface|string|null $date = null)                      Checks if the given date is in the same second as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentSecond()                                                                    Checks if the instance is in the same second as the current moment.
     * @method        bool                isNextSecond()                                                                       Checks if the instance is in the same second as the current moment next second.
     * @method        bool                isLastSecond()                                                                       Checks if the instance is in the same second as the current moment last second.
     * @method        bool                isSameMicro(Carbon|DateTimeInterface|string|null $date = null)                       Checks if the given date is in the same microsecond as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentMicro()                                                                     Checks if the instance is in the same microsecond as the current moment.
     * @method        bool                isNextMicro()                                                                        Checks if the instance is in the same microsecond as the current moment next microsecond.
     * @method        bool                isLastMicro()                                                                        Checks if the instance is in the same microsecond as the current moment last microsecond.
     * @method        bool                isSameMicrosecond(Carbon|DateTimeInterface|string|null $date = null)                 Checks if the given date is in the same microsecond as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentMicrosecond()                                                               Checks if the instance is in the same microsecond as the current moment.
     * @method        bool                isNextMicrosecond()                                                                  Checks if the instance is in the same microsecond as the current moment next microsecond.
     * @method        bool                isLastMicrosecond()                                                                  Checks if the instance is in the same microsecond as the current moment last microsecond.
     * @method        bool                isCurrentMonth()                                                                     Checks if the instance is in the same month as the current moment.
     * @method        bool                isNextMonth()                                                                        Checks if the instance is in the same month as the current moment next month.
     * @method        bool                isLastMonth()                                                                        Checks if the instance is in the same month as the current moment last month.
     * @method        bool                isCurrentQuarter()                                                                   Checks if the instance is in the same quarter as the current moment.
     * @method        bool                isNextQuarter()                                                                      Checks if the instance is in the same quarter as the current moment next quarter.
     * @method        bool                isLastQuarter()                                                                      Checks if the instance is in the same quarter as the current moment last quarter.
     * @method        bool                isSameDecade(Carbon|DateTimeInterface|string|null $date = null)                      Checks if the given date is in the same decade as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentDecade()                                                                    Checks if the instance is in the same decade as the current moment.
     * @method        bool                isNextDecade()                                                                       Checks if the instance is in the same decade as the current moment next decade.
     * @method        bool                isLastDecade()                                                                       Checks if the instance is in the same decade as the current moment last decade.
     * @method        bool                isSameCentury(Carbon|DateTimeInterface|string|null $date = null)                     Checks if the given date is in the same century as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentCentury()                                                                   Checks if the instance is in the same century as the current moment.
     * @method        bool                isNextCentury()                                                                      Checks if the instance is in the same century as the current moment next century.
     * @method        bool                isLastCentury()                                                                      Checks if the instance is in the same century as the current moment last century.
     * @method        bool                isSameMillennium(Carbon|DateTimeInterface|string|null $date = null)                  Checks if the given date is in the same millennium as the instance. If null passed, compare to now (with the same timezone).
     * @method        bool                isCurrentMillennium()                                                                Checks if the instance is in the same millennium as the current moment.
     * @method        bool                isNextMillennium()                                                                   Checks if the instance is in the same millennium as the current moment next millennium.
     * @method        bool                isLastMillennium()                                                                   Checks if the instance is in the same millennium as the current moment last millennium.
     * @method        $this               years(int $value)                                                                    Set current instance year to the given value.
     * @method        $this               year(int $value)                                                                     Set current instance year to the given value.
     * @method        $this               setYears(int $value)                                                                 Set current instance year to the given value.
     * @method        $this               setYear(int $value)                                                                  Set current instance year to the given value.
     * @method        $this               months(int $value)                                                                   Set current instance month to the given value.
     * @method        $this               month(int $value)                                                                    Set current instance month to the given value.
     * @method        $this               setMonths(int $value)                                                                Set current instance month to the given value.
     * @method        $this               setMonth(int $value)                                                                 Set current instance month to the given value.
     * @method        $this               days(int $value)                                                                     Set current instance day to the given value.
     * @method        $this               day(int $value)                                                                      Set current instance day to the given value.
     * @method        $this               setDays(int $value)                                                                  Set current instance day to the given value.
     * @method        $this               setDay(int $value)                                                                   Set current instance day to the given value.
     * @method        $this               hours(int $value)                                                                    Set current instance hour to the given value.
     * @method        $this               hour(int $value)                                                                     Set current instance hour to the given value.
     * @method        $this               setHours(int $value)                                                                 Set current instance hour to the given value.
     * @method        $this               setHour(int $value)                                                                  Set current instance hour to the given value.
     * @method        $this               minutes(int $value)                                                                  Set current instance minute to the given value.
     * @method        $this               minute(int $value)                                                                   Set current instance minute to the given value.
     * @method        $this               setMinutes(int $value)                                                               Set current instance minute to the given value.
     * @method        $this               setMinute(int $value)                                                                Set current instance minute to the given value.
     * @method        $this               seconds(int $value)                                                                  Set current instance second to the given value.
     * @method        $this               second(int $value)                                                                   Set current instance second to the given value.
     * @method        $this               setSeconds(int $value)                                                               Set current instance second to the given value.
     * @method        $this               setSecond(int $value)                                                                Set current instance second to the given value.
     * @method        $this               millis(int $value)                                                                   Set current instance millisecond to the given value.
     * @method        $this               milli(int $value)                                                                    Set current instance millisecond to the given value.
     * @method        $this               setMillis(int $value)                                                                Set current instance millisecond to the given value.
     * @method        $this               setMilli(int $value)                                                                 Set current instance millisecond to the given value.
     * @method        $this               milliseconds(int $value)                                                             Set current instance millisecond to the given value.
     * @method        $this               millisecond(int $value)                                                              Set current instance millisecond to the given value.
     * @method        $this               setMilliseconds(int $value)                                                          Set current instance millisecond to the given value.
     * @method        $this               setMillisecond(int $value)                                                           Set current instance millisecond to the given value.
     * @method        $this               micros(int $value)                                                                   Set current instance microsecond to the given value.
     * @method        $this               micro(int $value)                                                                    Set current instance microsecond to the given value.
     * @method        $this               setMicros(int $value)                                                                Set current instance microsecond to the given value.
     * @method        $this               setMicro(int $value)                                                                 Set current instance microsecond to the given value.
     * @method        $this               microseconds(int $value)                                                             Set current instance microsecond to the given value.
     * @method        $this               microsecond(int $value)                                                              Set current instance microsecond to the given value.
     * @method        $this               setMicroseconds(int $value)                                                          Set current instance microsecond to the given value.
     * @method        $this               setMicrosecond(int $value)                                                           Set current instance microsecond to the given value.
     * @method        $this               addYears(int $value = 1)                                                             Add years (the $value count passed in) to the instance (using date interval).
     * @method        $this               addYear()                                                                            Add one year to the instance (using date interval).
     * @method        $this               subYears(int $value = 1)                                                             Sub years (the $value count passed in) to the instance (using date interval).
     * @method        $this               subYear()                                                                            Sub one year to the instance (using date interval).
     * @method        $this               addYearsWithOverflow(int $value = 1)                                                 Add years (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addYearWithOverflow()                                                                Add one year to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subYearsWithOverflow(int $value = 1)                                                 Sub years (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subYearWithOverflow()                                                                Sub one year to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addYearsWithoutOverflow(int $value = 1)                                              Add years (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addYearWithoutOverflow()                                                             Add one year to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subYearsWithoutOverflow(int $value = 1)                                              Sub years (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subYearWithoutOverflow()                                                             Sub one year to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addYearsWithNoOverflow(int $value = 1)                                               Add years (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addYearWithNoOverflow()                                                              Add one year to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subYearsWithNoOverflow(int $value = 1)                                               Sub years (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subYearWithNoOverflow()                                                              Sub one year to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addYearsNoOverflow(int $value = 1)                                                   Add years (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addYearNoOverflow()                                                                  Add one year to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subYearsNoOverflow(int $value = 1)                                                   Sub years (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subYearNoOverflow()                                                                  Sub one year to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMonths(int $value = 1)                                                            Add months (the $value count passed in) to the instance (using date interval).
     * @method        $this               addMonth()                                                                           Add one month to the instance (using date interval).
     * @method        $this               subMonths(int $value = 1)                                                            Sub months (the $value count passed in) to the instance (using date interval).
     * @method        $this               subMonth()                                                                           Sub one month to the instance (using date interval).
     * @method        $this               addMonthsWithOverflow(int $value = 1)                                                Add months (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addMonthWithOverflow()                                                               Add one month to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subMonthsWithOverflow(int $value = 1)                                                Sub months (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subMonthWithOverflow()                                                               Sub one month to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addMonthsWithoutOverflow(int $value = 1)                                             Add months (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMonthWithoutOverflow()                                                            Add one month to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMonthsWithoutOverflow(int $value = 1)                                             Sub months (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMonthWithoutOverflow()                                                            Sub one month to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMonthsWithNoOverflow(int $value = 1)                                              Add months (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMonthWithNoOverflow()                                                             Add one month to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMonthsWithNoOverflow(int $value = 1)                                              Sub months (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMonthWithNoOverflow()                                                             Sub one month to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMonthsNoOverflow(int $value = 1)                                                  Add months (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMonthNoOverflow()                                                                 Add one month to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMonthsNoOverflow(int $value = 1)                                                  Sub months (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMonthNoOverflow()                                                                 Sub one month to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addDays(int $value = 1)                                                              Add days (the $value count passed in) to the instance (using date interval).
     * @method        $this               addDay()                                                                             Add one day to the instance (using date interval).
     * @method        $this               subDays(int $value = 1)                                                              Sub days (the $value count passed in) to the instance (using date interval).
     * @method        $this               subDay()                                                                             Sub one day to the instance (using date interval).
     * @method        $this               addHours(int $value = 1)                                                             Add hours (the $value count passed in) to the instance (using date interval).
     * @method        $this               addHour()                                                                            Add one hour to the instance (using date interval).
     * @method        $this               subHours(int $value = 1)                                                             Sub hours (the $value count passed in) to the instance (using date interval).
     * @method        $this               subHour()                                                                            Sub one hour to the instance (using date interval).
     * @method        $this               addMinutes(int $value = 1)                                                           Add minutes (the $value count passed in) to the instance (using date interval).
     * @method        $this               addMinute()                                                                          Add one minute to the instance (using date interval).
     * @method        $this               subMinutes(int $value = 1)                                                           Sub minutes (the $value count passed in) to the instance (using date interval).
     * @method        $this               subMinute()                                                                          Sub one minute to the instance (using date interval).
     * @method        $this               addSeconds(int $value = 1)                                                           Add seconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               addSecond()                                                                          Add one second to the instance (using date interval).
     * @method        $this               subSeconds(int $value = 1)                                                           Sub seconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               subSecond()                                                                          Sub one second to the instance (using date interval).
     * @method        $this               addMillis(int $value = 1)                                                            Add milliseconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               addMilli()                                                                           Add one millisecond to the instance (using date interval).
     * @method        $this               subMillis(int $value = 1)                                                            Sub milliseconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               subMilli()                                                                           Sub one millisecond to the instance (using date interval).
     * @method        $this               addMilliseconds(int $value = 1)                                                      Add milliseconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               addMillisecond()                                                                     Add one millisecond to the instance (using date interval).
     * @method        $this               subMilliseconds(int $value = 1)                                                      Sub milliseconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               subMillisecond()                                                                     Sub one millisecond to the instance (using date interval).
     * @method        $this               addMicros(int $value = 1)                                                            Add microseconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               addMicro()                                                                           Add one microsecond to the instance (using date interval).
     * @method        $this               subMicros(int $value = 1)                                                            Sub microseconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               subMicro()                                                                           Sub one microsecond to the instance (using date interval).
     * @method        $this               addMicroseconds(int $value = 1)                                                      Add microseconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               addMicrosecond()                                                                     Add one microsecond to the instance (using date interval).
     * @method        $this               subMicroseconds(int $value = 1)                                                      Sub microseconds (the $value count passed in) to the instance (using date interval).
     * @method        $this               subMicrosecond()                                                                     Sub one microsecond to the instance (using date interval).
     * @method        $this               addMillennia(int $value = 1)                                                         Add millennia (the $value count passed in) to the instance (using date interval).
     * @method        $this               addMillennium()                                                                      Add one millennium to the instance (using date interval).
     * @method        $this               subMillennia(int $value = 1)                                                         Sub millennia (the $value count passed in) to the instance (using date interval).
     * @method        $this               subMillennium()                                                                      Sub one millennium to the instance (using date interval).
     * @method        $this               addMillenniaWithOverflow(int $value = 1)                                             Add millennia (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addMillenniumWithOverflow()                                                          Add one millennium to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subMillenniaWithOverflow(int $value = 1)                                             Sub millennia (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subMillenniumWithOverflow()                                                          Sub one millennium to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addMillenniaWithoutOverflow(int $value = 1)                                          Add millennia (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMillenniumWithoutOverflow()                                                       Add one millennium to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMillenniaWithoutOverflow(int $value = 1)                                          Sub millennia (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMillenniumWithoutOverflow()                                                       Sub one millennium to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMillenniaWithNoOverflow(int $value = 1)                                           Add millennia (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMillenniumWithNoOverflow()                                                        Add one millennium to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMillenniaWithNoOverflow(int $value = 1)                                           Sub millennia (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMillenniumWithNoOverflow()                                                        Sub one millennium to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMillenniaNoOverflow(int $value = 1)                                               Add millennia (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addMillenniumNoOverflow()                                                            Add one millennium to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMillenniaNoOverflow(int $value = 1)                                               Sub millennia (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subMillenniumNoOverflow()                                                            Sub one millennium to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addCenturies(int $value = 1)                                                         Add centuries (the $value count passed in) to the instance (using date interval).
     * @method        $this               addCentury()                                                                         Add one century to the instance (using date interval).
     * @method        $this               subCenturies(int $value = 1)                                                         Sub centuries (the $value count passed in) to the instance (using date interval).
     * @method        $this               subCentury()                                                                         Sub one century to the instance (using date interval).
     * @method        $this               addCenturiesWithOverflow(int $value = 1)                                             Add centuries (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addCenturyWithOverflow()                                                             Add one century to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subCenturiesWithOverflow(int $value = 1)                                             Sub centuries (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subCenturyWithOverflow()                                                             Sub one century to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addCenturiesWithoutOverflow(int $value = 1)                                          Add centuries (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addCenturyWithoutOverflow()                                                          Add one century to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subCenturiesWithoutOverflow(int $value = 1)                                          Sub centuries (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subCenturyWithoutOverflow()                                                          Sub one century to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addCenturiesWithNoOverflow(int $value = 1)                                           Add centuries (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addCenturyWithNoOverflow()                                                           Add one century to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subCenturiesWithNoOverflow(int $value = 1)                                           Sub centuries (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subCenturyWithNoOverflow()                                                           Sub one century to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addCenturiesNoOverflow(int $value = 1)                                               Add centuries (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addCenturyNoOverflow()                                                               Add one century to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subCenturiesNoOverflow(int $value = 1)                                               Sub centuries (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subCenturyNoOverflow()                                                               Sub one century to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addDecades(int $value = 1)                                                           Add decades (the $value count passed in) to the instance (using date interval).
     * @method        $this               addDecade()                                                                          Add one decade to the instance (using date interval).
     * @method        $this               subDecades(int $value = 1)                                                           Sub decades (the $value count passed in) to the instance (using date interval).
     * @method        $this               subDecade()                                                                          Sub one decade to the instance (using date interval).
     * @method        $this               addDecadesWithOverflow(int $value = 1)                                               Add decades (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addDecadeWithOverflow()                                                              Add one decade to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subDecadesWithOverflow(int $value = 1)                                               Sub decades (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subDecadeWithOverflow()                                                              Sub one decade to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addDecadesWithoutOverflow(int $value = 1)                                            Add decades (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addDecadeWithoutOverflow()                                                           Add one decade to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subDecadesWithoutOverflow(int $value = 1)                                            Sub decades (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subDecadeWithoutOverflow()                                                           Sub one decade to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addDecadesWithNoOverflow(int $value = 1)                                             Add decades (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addDecadeWithNoOverflow()                                                            Add one decade to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subDecadesWithNoOverflow(int $value = 1)                                             Sub decades (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subDecadeWithNoOverflow()                                                            Sub one decade to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addDecadesNoOverflow(int $value = 1)                                                 Add decades (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addDecadeNoOverflow()                                                                Add one decade to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subDecadesNoOverflow(int $value = 1)                                                 Sub decades (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subDecadeNoOverflow()                                                                Sub one decade to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addQuarters(int $value = 1)                                                          Add quarters (the $value count passed in) to the instance (using date interval).
     * @method        $this               addQuarter()                                                                         Add one quarter to the instance (using date interval).
     * @method        $this               subQuarters(int $value = 1)                                                          Sub quarters (the $value count passed in) to the instance (using date interval).
     * @method        $this               subQuarter()                                                                         Sub one quarter to the instance (using date interval).
     * @method        $this               addQuartersWithOverflow(int $value = 1)                                              Add quarters (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addQuarterWithOverflow()                                                             Add one quarter to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subQuartersWithOverflow(int $value = 1)                                              Sub quarters (the $value count passed in) to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               subQuarterWithOverflow()                                                             Sub one quarter to the instance (using date interval) with overflow explicitly allowed.
     * @method        $this               addQuartersWithoutOverflow(int $value = 1)                                           Add quarters (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addQuarterWithoutOverflow()                                                          Add one quarter to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subQuartersWithoutOverflow(int $value = 1)                                           Sub quarters (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subQuarterWithoutOverflow()                                                          Sub one quarter to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addQuartersWithNoOverflow(int $value = 1)                                            Add quarters (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addQuarterWithNoOverflow()                                                           Add one quarter to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subQuartersWithNoOverflow(int $value = 1)                                            Sub quarters (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subQuarterWithNoOverflow()                                                           Sub one quarter to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addQuartersNoOverflow(int $value = 1)                                                Add quarters (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addQuarterNoOverflow()                                                               Add one quarter to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subQuartersNoOverflow(int $value = 1)                                                Sub quarters (the $value count passed in) to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               subQuarterNoOverflow()                                                               Sub one quarter to the instance (using date interval) with overflow explicitly forbidden.
     * @method        $this               addWeeks(int $value = 1)                                                             Add weeks (the $value count passed in) to the instance (using date interval).
     * @method        $this               addWeek()                                                                            Add one week to the instance (using date interval).
     * @method        $this               subWeeks(int $value = 1)                                                             Sub weeks (the $value count passed in) to the instance (using date interval).
     * @method        $this               subWeek()                                                                            Sub one week to the instance (using date interval).
     * @method        $this               addWeekdays(int $value = 1)                                                          Add weekdays (the $value count passed in) to the instance (using date interval).
     * @method        $this               addWeekday()                                                                         Add one weekday to the instance (using date interval).
     * @method        $this               subWeekdays(int $value = 1)                                                          Sub weekdays (the $value count passed in) to the instance (using date interval).
     * @method        $this               subWeekday()                                                                         Sub one weekday to the instance (using date interval).
     * @method        $this               addRealMicros(int $value = 1)                                                        Add microseconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealMicro()                                                                       Add one microsecond to the instance (using timestamp).
     * @method        $this               subRealMicros(int $value = 1)                                                        Sub microseconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealMicro()                                                                       Sub one microsecond to the instance (using timestamp).
     * @method        CarbonPeriod        microsUntil($endDate = null, int $factor = 1)                                        Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each microsecond or every X microseconds if a factor is given.
     * @method        $this               addRealMicroseconds(int $value = 1)                                                  Add microseconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealMicrosecond()                                                                 Add one microsecond to the instance (using timestamp).
     * @method        $this               subRealMicroseconds(int $value = 1)                                                  Sub microseconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealMicrosecond()                                                                 Sub one microsecond to the instance (using timestamp).
     * @method        CarbonPeriod        microsecondsUntil($endDate = null, int $factor = 1)                                  Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each microsecond or every X microseconds if a factor is given.
     * @method        $this               addRealMillis(int $value = 1)                                                        Add milliseconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealMilli()                                                                       Add one millisecond to the instance (using timestamp).
     * @method        $this               subRealMillis(int $value = 1)                                                        Sub milliseconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealMilli()                                                                       Sub one millisecond to the instance (using timestamp).
     * @method        CarbonPeriod        millisUntil($endDate = null, int $factor = 1)                                        Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each millisecond or every X milliseconds if a factor is given.
     * @method        $this               addRealMilliseconds(int $value = 1)                                                  Add milliseconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealMillisecond()                                                                 Add one millisecond to the instance (using timestamp).
     * @method        $this               subRealMilliseconds(int $value = 1)                                                  Sub milliseconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealMillisecond()                                                                 Sub one millisecond to the instance (using timestamp).
     * @method        CarbonPeriod        millisecondsUntil($endDate = null, int $factor = 1)                                  Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each millisecond or every X milliseconds if a factor is given.
     * @method        $this               addRealSeconds(int $value = 1)                                                       Add seconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealSecond()                                                                      Add one second to the instance (using timestamp).
     * @method        $this               subRealSeconds(int $value = 1)                                                       Sub seconds (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealSecond()                                                                      Sub one second to the instance (using timestamp).
     * @method        CarbonPeriod        secondsUntil($endDate = null, int $factor = 1)                                       Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each second or every X seconds if a factor is given.
     * @method        $this               addRealMinutes(int $value = 1)                                                       Add minutes (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealMinute()                                                                      Add one minute to the instance (using timestamp).
     * @method        $this               subRealMinutes(int $value = 1)                                                       Sub minutes (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealMinute()                                                                      Sub one minute to the instance (using timestamp).
     * @method        CarbonPeriod        minutesUntil($endDate = null, int $factor = 1)                                       Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each minute or every X minutes if a factor is given.
     * @method        $this               addRealHours(int $value = 1)                                                         Add hours (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealHour()                                                                        Add one hour to the instance (using timestamp).
     * @method        $this               subRealHours(int $value = 1)                                                         Sub hours (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealHour()                                                                        Sub one hour to the instance (using timestamp).
     * @method        CarbonPeriod        hoursUntil($endDate = null, int $factor = 1)                                         Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each hour or every X hours if a factor is given.
     * @method        $this               addRealDays(int $value = 1)                                                          Add days (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealDay()                                                                         Add one day to the instance (using timestamp).
     * @method        $this               subRealDays(int $value = 1)                                                          Sub days (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealDay()                                                                         Sub one day to the instance (using timestamp).
     * @method        CarbonPeriod        daysUntil($endDate = null, int $factor = 1)                                          Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each day or every X days if a factor is given.
     * @method        $this               addRealWeeks(int $value = 1)                                                         Add weeks (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealWeek()                                                                        Add one week to the instance (using timestamp).
     * @method        $this               subRealWeeks(int $value = 1)                                                         Sub weeks (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealWeek()                                                                        Sub one week to the instance (using timestamp).
     * @method        CarbonPeriod        weeksUntil($endDate = null, int $factor = 1)                                         Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each week or every X weeks if a factor is given.
     * @method        $this               addRealMonths(int $value = 1)                                                        Add months (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealMonth()                                                                       Add one month to the instance (using timestamp).
     * @method        $this               subRealMonths(int $value = 1)                                                        Sub months (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealMonth()                                                                       Sub one month to the instance (using timestamp).
     * @method        CarbonPeriod        monthsUntil($endDate = null, int $factor = 1)                                        Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each month or every X months if a factor is given.
     * @method        $this               addRealQuarters(int $value = 1)                                                      Add quarters (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealQuarter()                                                                     Add one quarter to the instance (using timestamp).
     * @method        $this               subRealQuarters(int $value = 1)                                                      Sub quarters (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealQuarter()                                                                     Sub one quarter to the instance (using timestamp).
     * @method        CarbonPeriod        quartersUntil($endDate = null, int $factor = 1)                                      Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each quarter or every X quarters if a factor is given.
     * @method        $this               addRealYears(int $value = 1)                                                         Add years (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealYear()                                                                        Add one year to the instance (using timestamp).
     * @method        $this               subRealYears(int $value = 1)                                                         Sub years (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealYear()                                                                        Sub one year to the instance (using timestamp).
     * @method        CarbonPeriod        yearsUntil($endDate = null, int $factor = 1)                                         Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each year or every X years if a factor is given.
     * @method        $this               addRealDecades(int $value = 1)                                                       Add decades (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealDecade()                                                                      Add one decade to the instance (using timestamp).
     * @method        $this               subRealDecades(int $value = 1)                                                       Sub decades (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealDecade()                                                                      Sub one decade to the instance (using timestamp).
     * @method        CarbonPeriod        decadesUntil($endDate = null, int $factor = 1)                                       Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each decade or every X decades if a factor is given.
     * @method        $this               addRealCenturies(int $value = 1)                                                     Add centuries (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealCentury()                                                                     Add one century to the instance (using timestamp).
     * @method        $this               subRealCenturies(int $value = 1)                                                     Sub centuries (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealCentury()                                                                     Sub one century to the instance (using timestamp).
     * @method        CarbonPeriod        centuriesUntil($endDate = null, int $factor = 1)                                     Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each century or every X centuries if a factor is given.
     * @method        $this               addRealMillennia(int $value = 1)                                                     Add millennia (the $value count passed in) to the instance (using timestamp).
     * @method        $this               addRealMillennium()                                                                  Add one millennium to the instance (using timestamp).
     * @method        $this               subRealMillennia(int $value = 1)                                                     Sub millennia (the $value count passed in) to the instance (using timestamp).
     * @method        $this               subRealMillennium()                                                                  Sub one millennium to the instance (using timestamp).
     * @method        CarbonPeriod        millenniaUntil($endDate = null, int $factor = 1)                                     Return an iterable period from current date to given end (string, DateTime or Carbon instance) for each millennium or every X millennia if a factor is given.
     * @method        $this               roundYear(float $precision = 1, string $function = "round")                          Round the current instance year with given precision using the given function.
     * @method        $this               roundYears(float $precision = 1, string $function = "round")                         Round the current instance year with given precision using the given function.
     * @method        $this               floorYear(float $precision = 1)                                                      Truncate the current instance year with given precision.
     * @method        $this               floorYears(float $precision = 1)                                                     Truncate the current instance year with given precision.
     * @method        $this               ceilYear(float $precision = 1)                                                       Ceil the current instance year with given precision.
     * @method        $this               ceilYears(float $precision = 1)                                                      Ceil the current instance year with given precision.
     * @method        $this               roundMonth(float $precision = 1, string $function = "round")                         Round the current instance month with given precision using the given function.
     * @method        $this               roundMonths(float $precision = 1, string $function = "round")                        Round the current instance month with given precision using the given function.
     * @method        $this               floorMonth(float $precision = 1)                                                     Truncate the current instance month with given precision.
     * @method        $this               floorMonths(float $precision = 1)                                                    Truncate the current instance month with given precision.
     * @method        $this               ceilMonth(float $precision = 1)                                                      Ceil the current instance month with given precision.
     * @method        $this               ceilMonths(float $precision = 1)                                                     Ceil the current instance month with given precision.
     * @method        $this               roundDay(float $precision = 1, string $function = "round")                           Round the current instance day with given precision using the given function.
     * @method        $this               roundDays(float $precision = 1, string $function = "round")                          Round the current instance day with given precision using the given function.
     * @method        $this               floorDay(float $precision = 1)                                                       Truncate the current instance day with given precision.
     * @method        $this               floorDays(float $precision = 1)                                                      Truncate the current instance day with given precision.
     * @method        $this               ceilDay(float $precision = 1)                                                        Ceil the current instance day with given precision.
     * @method        $this               ceilDays(float $precision = 1)                                                       Ceil the current instance day with given precision.
     * @method        $this               roundHour(float $precision = 1, string $function = "round")                          Round the current instance hour with given precision using the given function.
     * @method        $this               roundHours(float $precision = 1, string $function = "round")                         Round the current instance hour with given precision using the given function.
     * @method        $this               floorHour(float $precision = 1)                                                      Truncate the current instance hour with given precision.
     * @method        $this               floorHours(float $precision = 1)                                                     Truncate the current instance hour with given precision.
     * @method        $this               ceilHour(float $precision = 1)                                                       Ceil the current instance hour with given precision.
     * @method        $this               ceilHours(float $precision = 1)                                                      Ceil the current instance hour with given precision.
     * @method        $this               roundMinute(float $precision = 1, string $function = "round")                        Round the current instance minute with given precision using the given function.
     * @method        $this               roundMinutes(float $precision = 1, string $function = "round")                       Round the current instance minute with given precision using the given function.
     * @method        $this               floorMinute(float $precision = 1)                                                    Truncate the current instance minute with given precision.
     * @method        $this               floorMinutes(float $precision = 1)                                                   Truncate the current instance minute with given precision.
     * @method        $this               ceilMinute(float $precision = 1)                                                     Ceil the current instance minute with given precision.
     * @method        $this               ceilMinutes(float $precision = 1)                                                    Ceil the current instance minute with given precision.
     * @method        $this               roundSecond(float $precision = 1, string $function = "round")                        Round the current instance second with given precision using the given function.
     * @method        $this               roundSeconds(float $precision = 1, string $function = "round")                       Round the current instance second with given precision using the given function.
     * @method        $this               floorSecond(float $precision = 1)                                                    Truncate the current instance second with given precision.
     * @method        $this               floorSeconds(float $precision = 1)                                                   Truncate the current instance second with given precision.
     * @method        $this               ceilSecond(float $precision = 1)                                                     Ceil the current instance second with given precision.
     * @method        $this               ceilSeconds(float $precision = 1)                                                    Ceil the current instance second with given precision.
     * @method        $this               roundMillennium(float $precision = 1, string $function = "round")                    Round the current instance millennium with given precision using the given function.
     * @method        $this               roundMillennia(float $precision = 1, string $function = "round")                     Round the current instance millennium with given precision using the given function.
     * @method        $this               floorMillennium(float $precision = 1)                                                Truncate the current instance millennium with given precision.
     * @method        $this               floorMillennia(float $precision = 1)                                                 Truncate the current instance millennium with given precision.
     * @method        $this               ceilMillennium(float $precision = 1)                                                 Ceil the current instance millennium with given precision.
     * @method        $this               ceilMillennia(float $precision = 1)                                                  Ceil the current instance millennium with given precision.
     * @method        $this               roundCentury(float $precision = 1, string $function = "round")                       Round the current instance century with given precision using the given function.
     * @method        $this               roundCenturies(float $precision = 1, string $function = "round")                     Round the current instance century with given precision using the given function.
     * @method        $this               floorCentury(float $precision = 1)                                                   Truncate the current instance century with given precision.
     * @method        $this               floorCenturies(float $precision = 1)                                                 Truncate the current instance century with given precision.
     * @method        $this               ceilCentury(float $precision = 1)                                                    Ceil the current instance century with given precision.
     * @method        $this               ceilCenturies(float $precision = 1)                                                  Ceil the current instance century with given precision.
     * @method        $this               roundDecade(float $precision = 1, string $function = "round")                        Round the current instance decade with given precision using the given function.
     * @method        $this               roundDecades(float $precision = 1, string $function = "round")                       Round the current instance decade with given precision using the given function.
     * @method        $this               floorDecade(float $precision = 1)                                                    Truncate the current instance decade with given precision.
     * @method        $this               floorDecades(float $precision = 1)                                                   Truncate the current instance decade with given precision.
     * @method        $this               ceilDecade(float $precision = 1)                                                     Ceil the current instance decade with given precision.
     * @method        $this               ceilDecades(float $precision = 1)                                                    Ceil the current instance decade with given precision.
     * @method        $this               roundQuarter(float $precision = 1, string $function = "round")                       Round the current instance quarter with given precision using the given function.
     * @method        $this               roundQuarters(float $precision = 1, string $function = "round")                      Round the current instance quarter with given precision using the given function.
     * @method        $this               floorQuarter(float $precision = 1)                                                   Truncate the current instance quarter with given precision.
     * @method        $this               floorQuarters(float $precision = 1)                                                  Truncate the current instance quarter with given precision.
     * @method        $this               ceilQuarter(float $precision = 1)                                                    Ceil the current instance quarter with given precision.
     * @method        $this               ceilQuarters(float $precision = 1)                                                   Ceil the current instance quarter with given precision.
     * @method        $this               roundMillisecond(float $precision = 1, string $function = "round")                   Round the current instance millisecond with given precision using the given function.
     * @method        $this               roundMilliseconds(float $precision = 1, string $function = "round")                  Round the current instance millisecond with given precision using the given function.
     * @method        $this               floorMillisecond(float $precision = 1)                                               Truncate the current instance millisecond with given precision.
     * @method        $this               floorMilliseconds(float $precision = 1)                                              Truncate the current instance millisecond with given precision.
     * @method        $this               ceilMillisecond(float $precision = 1)                                                Ceil the current instance millisecond with given precision.
     * @method        $this               ceilMilliseconds(float $precision = 1)                                               Ceil the current instance millisecond with given precision.
     * @method        $this               roundMicrosecond(float $precision = 1, string $function = "round")                   Round the current instance microsecond with given precision using the given function.
     * @method        $this               roundMicroseconds(float $precision = 1, string $function = "round")                  Round the current instance microsecond with given precision using the given function.
     * @method        $this               floorMicrosecond(float $precision = 1)                                               Truncate the current instance microsecond with given precision.
     * @method        $this               floorMicroseconds(float $precision = 1)                                              Truncate the current instance microsecond with given precision.
     * @method        $this               ceilMicrosecond(float $precision = 1)                                                Ceil the current instance microsecond with given precision.
     * @method        $this               ceilMicroseconds(float $precision = 1)                                               Ceil the current instance microsecond with given precision.
     * @method        string              shortAbsoluteDiffForHumans(DateTimeInterface $other = null, int $parts = 1)          Get the difference (short format, 'Absolute' mode) in a human readable format in the current locale. ($other and $parts parameters can be swapped.)
     * @method        string              longAbsoluteDiffForHumans(DateTimeInterface $other = null, int $parts = 1)           Get the difference (long format, 'Absolute' mode) in a human readable format in the current locale. ($other and $parts parameters can be swapped.)
     * @method        string              shortRelativeDiffForHumans(DateTimeInterface $other = null, int $parts = 1)          Get the difference (short format, 'Relative' mode) in a human readable format in the current locale. ($other and $parts parameters can be swapped.)
     * @method        string              longRelativeDiffForHumans(DateTimeInterface $other = null, int $parts = 1)           Get the difference (long format, 'Relative' mode) in a human readable format in the current locale. ($other and $parts parameters can be swapped.)
     * @method        string              shortRelativeToNowDiffForHumans(DateTimeInterface $other = null, int $parts = 1)     Get the difference (short format, 'RelativeToNow' mode) in a human readable format in the current locale. ($other and $parts parameters can be swapped.)
     * @method        string              longRelativeToNowDiffForHumans(DateTimeInterface $other = null, int $parts = 1)      Get the difference (long format, 'RelativeToNow' mode) in a human readable format in the current locale. ($other and $parts parameters can be swapped.)
     * @method        string              shortRelativeToOtherDiffForHumans(DateTimeInterface $other = null, int $parts = 1)   Get the difference (short format, 'RelativeToOther' mode) in a human readable format in the current locale. ($other and $parts parameters can be swapped.)
     * @method        string              longRelativeToOtherDiffForHumans(DateTimeInterface $other = null, int $parts = 1)    Get the difference (long format, 'RelativeToOther' mode) in a human readable format in the current locale. ($other and $parts parameters can be swapped.)
     * @method        static Carbon|false createFromFormat(string $format, string $time, string|DateTimeZone $timezone = null) Parse a string into a new Carbon object according to the specified format.
     * @method        static Carbon       __set_state(array $array)                                                            https://php.net/manual/en/datetime.set-state.php
     *
     * </autodoc>
     */
    class Carbon extends DateTime implements CarbonInterface
    {
        use Date;

        /**
         * Returns true if the current class/instance is mutable.
         *
         * @return bool
         */
        public static function isMutable()
        {
            return true;
        }

    }
}

namespace Illuminate\Database\Eloquent\Factories {

    trait HasFactory
    {
        /**
         * Get a new factory instance for the model.
         *
         * @param mixed $parameters
         * @return \Illuminate\Database\Eloquent\Factories\Factory
         */
        public static function factory(...$parameters)
        {
            $factory = static::newFactory() ?: Factory::factoryForModel(get_called_class());

            return $factory
                ->count(is_numeric($parameters[0] ?? null) ? $parameters[0] : null)
                ->state(is_array($parameters[0] ?? null) ? $parameters[0] : ($parameters[1] ?? []));
        }

        /**
         * Create a new factory instance for the model.
         *
         * @return \Illuminate\Database\Eloquent\Factories\Factory
         */
        protected static function newFactory()
        {
            //
        }
    }
}

namespace Illuminate\Database\Eloquent {

    class Collection extends BaseCollection implements QueueableCollection
    {
        /**
         * Find a model in the collection by key.
         *
         * @param mixed $key
         * @param mixed $default
         * @return \Illuminate\Database\Eloquent\Model|static|null
         */
        public function find($key, $default = null)
        {
            if ($key instanceof Model) {
                $key = $key->getKey();
            }

            if ($key instanceof Arrayable) {
                $key = $key->toArray();
            }

            if (is_array($key)) {
                if ($this->isEmpty()) {
                    return new static;
                }

                return $this->whereIn($this->first()->getKeyName(), $key);
            }

            return Arr::first($this->items, function ($model) use ($key) {
                return $model->getKey() == $key;
            }, $default);
        }

        /**
         * Load a set of relationships onto the collection.
         *
         * @param array|string $relations
         * @return $this
         */
        public function load($relations)
        {
            if ($this->isNotEmpty()) {
                if (is_string($relations)) {
                    $relations = func_get_args();
                }

                $query = $this->first()->newQueryWithoutRelationships()->with($relations);

                $this->items = $query->eagerLoadRelations($this->items);
            }

            return $this;
        }

        /**
         * Load a set of aggregations over relationship's column onto the collection.
         *
         * @param array|string $relations
         * @param string $column
         * @param string $function
         * @return $this
         */
        public function loadAggregate($relations, $column, $function = null)
        {
            if ($this->isEmpty()) {
                return $this;
            }

            $models = $this->first()->newModelQuery()
                ->whereKey($this->modelKeys())
                ->select($this->first()->getKeyName())
                ->withAggregate($relations, $column, $function)
                ->get()
                ->keyBy($this->first()->getKeyName());

            $attributes = Arr::except(
                array_keys($models->first()->getAttributes()),
                $models->first()->getKeyName()
            );

            $this->each(function ($model) use ($models, $attributes) {
                $extraAttributes = Arr::only($models->get($model->getKey())->getAttributes(), $attributes);

                $model->forceFill($extraAttributes)->syncOriginalAttributes($attributes);
            });

            return $this;
        }

        /**
         * Load a set of relationship counts onto the collection.
         *
         * @param array|string $relations
         * @return $this
         */
        public function loadCount($relations)
        {
            return $this->loadAggregate($relations, '*', 'count');
        }

        /**
         * Load a set of relationship's max column values onto the collection.
         *
         * @param array|string $relations
         * @param string $column
         * @return $this
         */
        public function loadMax($relations, $column)
        {
            return $this->loadAggregate($relations, $column, 'max');
        }

        /**
         * Load a set of relationship's min column values onto the collection.
         *
         * @param array|string $relations
         * @param string $column
         * @return $this
         */
        public function loadMin($relations, $column)
        {
            return $this->loadAggregate($relations, $column, 'min');
        }

        /**
         * Load a set of relationship's column summations onto the collection.
         *
         * @param array|string $relations
         * @param string $column
         * @return $this
         */
        public function loadSum($relations, $column)
        {
            return $this->loadAggregate($relations, $column, 'sum');
        }

        /**
         * Load a set of relationship's average column values onto the collection.
         *
         * @param array|string $relations
         * @param string $column
         * @return $this
         */
        public function loadAvg($relations, $column)
        {
            return $this->loadAggregate($relations, $column, 'avg');
        }

        /**
         * Load a set of related existences onto the collection.
         *
         * @param array|string $relations
         * @return $this
         */
        public function loadExists($relations)
        {
            return $this->loadAggregate($relations, '*', 'exists');
        }

        /**
         * Load a set of relationships onto the collection if they are not already eager loaded.
         *
         * @param array|string $relations
         * @return $this
         */
        public function loadMissing($relations)
        {
            if (is_string($relations)) {
                $relations = func_get_args();
            }

            foreach ($relations as $key => $value) {
                if (is_numeric($key)) {
                    $key = $value;
                }

                $segments = explode('.', explode(':', $key)[0]);

                if (Str::contains($key, ':')) {
                    $segments[count($segments) - 1] .= ':' . explode(':', $key)[1];
                }

                $path = [];

                foreach ($segments as $segment) {
                    $path[] = [$segment => $segment];
                }

                if (is_callable($value)) {
                    $path[count($segments) - 1][end($segments)] = $value;
                }

                $this->loadMissingRelation($this, $path);
            }

            return $this;
        }

        /**
         * Load a relationship path if it is not already eager loaded.
         *
         * @param \Illuminate\Database\Eloquent\Collection $models
         * @param array $path
         * @return void
         */
        protected function loadMissingRelation(self $models, array $path)
        {
            $relation = array_shift($path);

            $name = explode(':', key($relation))[0];

            if (is_string(reset($relation))) {
                $relation = reset($relation);
            }

            $models->filter(function ($model) use ($name) {
                return !is_null($model) && !$model->relationLoaded($name);
            })->load($relation);

            if (empty($path)) {
                return;
            }

            $models = $models->pluck($name)->whereNotNull();

            if ($models->first() instanceof BaseCollection) {
                $models = $models->collapse();
            }

            $this->loadMissingRelation(new static($models), $path);
        }

        /**
         * Load a set of relationships onto the mixed relationship collection.
         *
         * @param string $relation
         * @param array $relations
         * @return $this
         */
        public function loadMorph($relation, $relations)
        {
            $this->pluck($relation)
                ->filter()
                ->groupBy(function ($model) {
                    return get_class($model);
                })
                ->each(function ($models, $className) use ($relations) {
                    static::make($models)->load($relations[$className] ?? []);
                });

            return $this;
        }

        /**
         * Load a set of relationship counts onto the mixed relationship collection.
         *
         * @param string $relation
         * @param array $relations
         * @return $this
         */
        public function loadMorphCount($relation, $relations)
        {
            $this->pluck($relation)
                ->filter()
                ->groupBy(function ($model) {
                    return get_class($model);
                })
                ->each(function ($models, $className) use ($relations) {
                    static::make($models)->loadCount($relations[$className] ?? []);
                });

            return $this;
        }

        /**
         * Determine if a key exists in the collection.
         *
         * @param mixed $key
         * @param mixed $operator
         * @param mixed $value
         * @return bool
         */
        public function contains($key, $operator = null, $value = null)
        {
            if (func_num_args() > 1 || $this->useAsCallable($key)) {
                return parent::contains(...func_get_args());
            }

            if ($key instanceof Model) {
                return parent::contains(function ($model) use ($key) {
                    return $model->is($key);
                });
            }

            return parent::contains(function ($model) use ($key) {
                return $model->getKey() == $key;
            });
        }

        /**
         * Get the array of primary keys.
         *
         * @return array
         */
        public function modelKeys()
        {
            return array_map(function ($model) {
                return $model->getKey();
            }, $this->items);
        }

        /**
         * Merge the collection with the given items.
         *
         * @param \ArrayAccess|array $items
         * @return static
         */
        public function merge($items)
        {
            $dictionary = $this->getDictionary();

            foreach ($items as $item) {
                $dictionary[$item->getKey()] = $item;
            }

            return new static(array_values($dictionary));
        }

        /**
         * Run a map over each of the items.
         *
         * @param callable $callback
         * @return \Illuminate\Support\Collection|static
         */
        public function map(callable $callback)
        {
            $result = parent::map($callback);

            return $result->contains(function ($item) {
                return !$item instanceof Model;
            }) ? $result->toBase() : $result;
        }

        /**
         * Run an associative map over each of the items.
         *
         * The callback should return an associative array with a single key / value pair.
         *
         * @param callable $callback
         * @return \Illuminate\Support\Collection|static
         */
        public function mapWithKeys(callable $callback)
        {
            $result = parent::mapWithKeys($callback);

            return $result->contains(function ($item) {
                return !$item instanceof Model;
            }) ? $result->toBase() : $result;
        }

        /**
         * Reload a fresh model instance from the database for all the entities.
         *
         * @param array|string $with
         * @return static
         */
        public function fresh($with = [])
        {
            if ($this->isEmpty()) {
                return new static;
            }

            $model = $this->first();

            $freshModels = $model->newQueryWithoutScopes()
                ->with(is_string($with) ? func_get_args() : $with)
                ->whereIn($model->getKeyName(), $this->modelKeys())
                ->get()
                ->getDictionary();

            return $this->filter(function ($model) use ($freshModels) {
                return $model->exists && isset($freshModels[$model->getKey()]);
            })
                ->map(function ($model) use ($freshModels) {
                    return $freshModels[$model->getKey()];
                });
        }

        /**
         * Diff the collection with the given items.
         *
         * @param \ArrayAccess|array $items
         * @return static
         */
        public function diff($items)
        {
            $diff = new static;

            $dictionary = $this->getDictionary($items);

            foreach ($this->items as $item) {
                if (!isset($dictionary[$item->getKey()])) {
                    $diff->add($item);
                }
            }

            return $diff;
        }

        /**
         * Intersect the collection with the given items.
         *
         * @param \ArrayAccess|array $items
         * @return static
         */
        public function intersect($items)
        {
            $intersect = new static;

            if (empty($items)) {
                return $intersect;
            }

            $dictionary = $this->getDictionary($items);

            foreach ($this->items as $item) {
                if (isset($dictionary[$item->getKey()])) {
                    $intersect->add($item);
                }
            }

            return $intersect;
        }

        /**
         * Return only unique items from the collection.
         *
         * @param string|callable|null $key
         * @param bool $strict
         * @return static
         */
        public function unique($key = null, $strict = false)
        {
            if (!is_null($key)) {
                return parent::unique($key, $strict);
            }

            return new static(array_values($this->getDictionary()));
        }

        /**
         * Returns only the models from the collection with the specified keys.
         *
         * @param mixed $keys
         * @return static
         */
        public function only($keys)
        {
            if (is_null($keys)) {
                return new static($this->items);
            }

            $dictionary = Arr::only($this->getDictionary(), $keys);

            return new static(array_values($dictionary));
        }

        /**
         * Returns all models in the collection except the models with specified keys.
         *
         * @param mixed $keys
         * @return static
         */
        public function except($keys)
        {
            $dictionary = Arr::except($this->getDictionary(), $keys);

            return new static(array_values($dictionary));
        }

        /**
         * Make the given, typically visible, attributes hidden across the entire collection.
         *
         * @param array|string $attributes
         * @return $this
         */
        public function makeHidden($attributes)
        {
            return $this->each->makeHidden($attributes);
        }

        /**
         * Make the given, typically hidden, attributes visible across the entire collection.
         *
         * @param array|string $attributes
         * @return $this
         */
        public function makeVisible($attributes)
        {
            return $this->each->makeVisible($attributes);
        }

        /**
         * Append an attribute across the entire collection.
         *
         * @param array|string $attributes
         * @return $this
         */
        public function append($attributes)
        {
            return $this->each->append($attributes);
        }

        /**
         * Get a dictionary keyed by primary keys.
         *
         * @param \ArrayAccess|array|null $items
         * @return array
         */
        public function getDictionary($items = null)
        {
            $items = is_null($items) ? $this->items : $items;

            $dictionary = [];

            foreach ($items as $value) {
                $dictionary[$value->getKey()] = $value;
            }

            return $dictionary;
        }

        /**
         * The following methods are intercepted to always return base collections.
         */

        /**
         * Get an array with the values of a given key.
         *
         * @param string|array $value
         * @param string|null $key
         * @return \Illuminate\Support\Collection
         */
        public function pluck($value, $key = null)
        {
            return $this->toBase()->pluck($value, $key);
        }

        /**
         * Get the keys of the collection items.
         *
         * @return \Illuminate\Support\Collection
         */
        public function keys()
        {
            return $this->toBase()->keys();
        }

        /**
         * Zip the collection together with one or more arrays.
         *
         * @param mixed ...$items
         * @return \Illuminate\Support\Collection
         */
        public function zip($items)
        {
            return $this->toBase()->zip(...func_get_args());
        }

        /**
         * Collapse the collection of items into a single array.
         *
         * @return \Illuminate\Support\Collection
         */
        public function collapse()
        {
            return $this->toBase()->collapse();
        }

        /**
         * Get a flattened array of the items in the collection.
         *
         * @param int $depth
         * @return \Illuminate\Support\Collection
         */
        public function flatten($depth = INF)
        {
            return $this->toBase()->flatten($depth);
        }

        /**
         * Flip the items in the collection.
         *
         * @return \Illuminate\Support\Collection
         */
        public function flip()
        {
            return $this->toBase()->flip();
        }

        /**
         * Pad collection to the specified length with a value.
         *
         * @param int $size
         * @param mixed $value
         * @return \Illuminate\Support\Collection
         */
        public function pad($size, $value)
        {
            return $this->toBase()->pad($size, $value);
        }

        /**
         * Get the comparison function to detect duplicates.
         *
         * @param bool $strict
         * @return \Closure
         */
        protected function duplicateComparator($strict)
        {
            return function ($a, $b) {
                return $a->is($b);
            };
        }

        /**
         * Get the type of the entities being queued.
         *
         * @return string|null
         *
         * @throws \LogicException
         */
        public function getQueueableClass()
        {
            if ($this->isEmpty()) {
                return;
            }

            $class = get_class($this->first());

            $this->each(function ($model) use ($class) {
                if (get_class($model) !== $class) {
                    throw new LogicException('Queueing collections with multiple model types is not supported.');
                }
            });

            return $class;
        }

        /**
         * Get the identifiers for all of the entities.
         *
         * @return array
         */
        public function getQueueableIds()
        {
            if ($this->isEmpty()) {
                return [];
            }

            return $this->first() instanceof QueueableEntity
                ? $this->map->getQueueableId()->all()
                : $this->modelKeys();
        }

        /**
         * Get the relationships of the entities being queued.
         *
         * @return array
         */
        public function getQueueableRelations()
        {
            if ($this->isEmpty()) {
                return [];
            }

            $relations = $this->map->getQueueableRelations()->all();

            if (count($relations) === 0 || $relations === [[]]) {
                return [];
            } elseif (count($relations) === 1) {
                return reset($relations);
            } else {
                return array_intersect(...array_values($relations));
            }
        }

        /**
         * Get the connection of the entities being queued.
         *
         * @return string|null
         *
         * @throws \LogicException
         */
        public function getQueueableConnection()
        {
            if ($this->isEmpty()) {
                return;
            }

            $connection = $this->first()->getConnectionName();

            $this->each(function ($model) use ($connection) {
                if ($model->getConnectionName() !== $connection) {
                    throw new LogicException('Queueing collections with multiple model connections is not supported.');
                }
            });

            return $connection;
        }

        /**
         * Get the Eloquent query builder from the collection.
         *
         * @return \Illuminate\Database\Eloquent\Builder
         *
         * @throws \LogicException
         */
        public function toQuery()
        {
            $model = $this->first();

            if (!$model) {
                throw new LogicException('Unable to create query for empty collection.');
            }

            $class = get_class($model);

            if ($this->filter(function ($model) use ($class) {
                return !$model instanceof $class;
            })->isNotEmpty()) {
                throw new LogicException('Unable to create query for collection with mixed types.');
            }

            return $model->newModelQuery()->whereKey($this->modelKeys());
        }
    }
}
