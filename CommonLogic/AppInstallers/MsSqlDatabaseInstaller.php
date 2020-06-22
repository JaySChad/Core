<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * Database AppInstaller for Apps with Microsoft SQL Server Database.
 * 
 * ## Encoding
 * 
 * This installer currently requires SQL files to be encoded as UTF8!!!
 * 
 * ## Transaction handling
 * 
 * NOTE: SQL Server seems to only support DDL rollbacks in certain stuations,
 * so (similarly to MySQL) we wrap each UP/DOWN script in a transaction - this
 * ensures, that if a script was performed successfully, all it's changes
 * are committed - DDL and DML. If not done so, most changes might get rolled
 * back if something in the next migration script goes wrong, while certain DDL
 * changes would remain due to their implicit commit.
 *
 * @author Ralf Mulansky
 *
 */
class MsSqlDatabaseInstaller extends MySqlDatabaseInstaller
{  
    private $sql_migrations_prefix = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::getSqlDbType()
     */
    protected function getSqlDbType() : string
    {
        return 'MsSQL';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::installDatabase()
     */
    protected function installDatabase(SqlDataConnectorInterface $connection, string $indent = '') : string
    {        
        $msg = '';
        try {
            $connection->connect();
        } catch (DataConnectionFailedError $e) {            
            $dbName = $connection->getDatabase();
            $connection->setDatabase('');
            $connection->connect();
            $database_create = "CREATE DATABASE {$dbName}";
            $connection->runSql($database_create);
            $database_use = "USE {$dbName};";
            $connection->runSql($database_use);
            $connection->disconnect();
            $connection->setDatabase($dbName);
            $msg = 'Database ' . $dbName . ' created! ';
        }
        return $indent . $msg;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlShowMigrationTable()
     */
    protected function buildSqlMigrationTableShow() : string
    {
        return <<<SQL

SELECT OBJECT_ID('{$this->getMigrationsTableName()}', 'U') AS id;
SQL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::ensureMigrationsTableExists()
     */
    protected function ensureMigrationsTableExists(SqlDataConnectorInterface $connection) : void
    {
        $sql = $this->buildSqlMigrationTableShow();
        $result = $connection->runSql($sql)->getResultArray();
        if ($result [0]['id'] === NULL) {
            try {
                $migrations_table_create = $this->buildSqlMigrationTableCreate();
                $this->runSqlMultiStatementScript($connection, $migrations_table_create);
                $this->getWorkbench()->getLogger()->debug('SQL migration table ' . $this->getMigrationsTableName() . ' created! ');
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
                throw new InstallerRuntimeError($this, "Generating Migration table '{$this->getMigrationsTableName()}' failed!");
            }
        }
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlCreateMigrationTable()
     */
    protected function buildSqlMigrationTableCreate() : string
    {
        $pkName = 'PK_' . parent::getMigrationsTableName() . '_id';
        return <<<SQL
        
CREATE TABLE {$this->getMigrationsTableName()}(
	[id] [int] IDENTITY(40,1) NOT NULL,
	[migration_name] [nvarchar](300) NOT NULL,
	[up_datetime] [datetime] NOT NULL,
	[up_script] [nvarchar](max) NOT NULL,
	[up_result] [nvarchar](max) NOT NULL,
	[down_datetime] [datetime] NULL,
	[down_script] [nvarchar](max) NOT NULL,
	[down_result] [nvarchar](max) NULL,
 CONSTRAINT [{$pkName}] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];
ALTER TABLE {$this->getMigrationsTableName()} ADD  DEFAULT (getdate()) FOR [up_datetime];
ALTER TABLE {$this->getMigrationsTableName()} ADD  DEFAULT (NULL) FOR [down_datetime];

SQL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlMigrationTableInsert()
     */
    protected function buildSqlMigrationTableInsert(string $migration_name, string $up_script, string $up_result_string, string $down_script) : string
    {
        return parent::buildSqlMigrationTableInsert($migration_name, $up_script, $up_result_string, $down_script) . "SELECT SCOPE_IDENTITY();";
    }
    
    
    /**
     * Set the prefix of the SQL table to store the migration log.
     *
     * @return string
     */
    public function setMigrationsTablePrefix(string $prefix) : AbstractSqlDatabaseInstaller
    {
        $this->sql_migrations_prefix = $prefix;
        return $this;
    }
    
    /**
     * Returns the prefix of the SQL table to store the migration log.
     *
     * @return string
     */
    public function getMigrationsTablePrefix() : ?string
    {
        if ($this->sql_migrations_prefix) {
            return $this->sql_migrations_prefix;
        }
        return 'dbo';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::getMigrationsTableName()
     */
    public function getMigrationsTableName() : string
    {
        return "[" . $this->getMigrationsTablePrefix() . "].[" . parent::getMigrationsTableName() . "]";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::escapeSqlStringValue()
     */
    protected function escapeSqlStringValue(string $value) : string
    {
        return str_replace("'", "''", $value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlFunctionNow()
     */
    protected function buildSqlFunctionNow() : string
    {
        return 'GETDATE()';
    }
}