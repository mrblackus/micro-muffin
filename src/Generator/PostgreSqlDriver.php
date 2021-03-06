<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mathieu.savy
 * Date: 21/08/13
 * Time: 11:58
 * To change this template use File | Settings | File Templates.
 */

namespace MicroMuffin\Generator;

use MicroMuffin\PDOS;
use MicroMuffin\Tools;
use \PDO;

class PostgreSqlDriver extends Driver
{
    protected function readDatabaseSchema()
    {
        $schema = new AbstractSchema($this);

        $tables = $this->readTables();
        $this->readPrimaryKeys($tables);
        $this->readForeignKeys($tables);
        $schema->setTables($tables);
        $schema->setStoredProcedures($this->readCustomStoredProcedures($tables));

        $this->abstractSchema = $schema;
    }

    /**
     * @return Table[]
     */
    private function readTables()
    {
        $pdo = PDOS::getInstance();

        //Getting all fields of all tables from selected schema
        $query = $pdo->prepare("
        SELECT
          table_name,
          column_name,
          column_default,
          data_type,
          pg_get_serial_sequence(table_name, column_name) AS sequence_name
        FROM
          information_schema.columns
        WHERE
          table_schema = '" . DBSCHEMA . "'
          AND table_name in (SELECT table_name
            FROM information_schema.tables
            WHERE table_schema= '" . DBSCHEMA . "'
            AND table_type='BASE TABLE'
          )
        ORDER BY
          table_name");
        $query->execute();

        $fields = $query->fetchAll();

        /** @var Table[] $tables */
        $tables = array();

        foreach ($fields as $f)
        {
            //Creating table if it doesn't exist yet
            if (!array_key_exists($f['table_name'], $tables))
            {
                $tables[$f['table_name']] = new Table($f['table_name']);
            }

            $table = & $tables[$f['table_name']];
            $field = new Field($f['column_name']);
            $field->setDefaultValue(is_null($f['sequence_name']) ? $f['column_default'] : null);
            $field->setType($f['data_type']);

            if (!is_null($f['sequence_name']))
            {
                $array = explode(DBSCHEMA . '.', $f['sequence_name']);
                $field->setSequence($array[1]);
            }
            $table->addField($field);
        }

        return $tables;
    }

    /**
     * @param Table[] $tables
     */
    private function readPrimaryKeys(&$tables)
    {
        $pdo = PDOS::getInstance();

        $query = $pdo->prepare("
        SELECT
            tc.table_name,
            ccu.column_name,
            c.data_type
        FROM
            information_schema.table_constraints AS tc
            INNER JOIN information_schema.constraint_column_usage AS ccu ON tc.constraint_name = ccu.constraint_name
            INNER JOIN information_schema.columns AS c ON c.column_name = ccu.column_name AND c.table_name = tc.table_name
        WHERE tc.constraint_type = 'PRIMARY KEY' AND tc.constraint_schema = '" . DBSCHEMA . "'
        ORDER BY tc.table_name, ccu.column_name");
        $query->execute();

        /** @var PrimaryKey[] $primaryKeys */
        $primaryKeys = array();

        /*
         * There is one primary key by table, but it can contains several columns, each iteration of this loop is for a column
         */
        foreach ($query->fetchAll() as $pk)
        {
            if (!array_key_exists($pk['table_name'], $primaryKeys))
                $primaryKeys[$pk['table_name']] = new PrimaryKey();

            $object = & $primaryKeys[$pk['table_name']];

            $field = new Field($pk['column_name']);
            $field->setType($pk['data_type']);
            $object->addField($field);
        }

        foreach ($primaryKeys as $table => $pk)
        {
            if (array_key_exists($table, $tables))
                $tables[$table]->setPrimaryKey($pk);
        }
    }

    /**
     * @param Table[] $tables
     */
    private function readForeignKeys(&$tables)
    {
        $pdo = PDOS::getInstance();

        $query = $pdo->prepare("
        SELECT
            tc.table_name,
            kcu.column_name,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name,
            c.data_type AS foreign_column_type
        FROM
            information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
            JOIN information_schema.columns AS c ON c.table_name = ccu.table_name AND c.column_name = ccu.column_name
        WHERE constraint_type = 'FOREIGN KEY';");

        $query->execute();

        foreach ($query->fetchAll() as $fk)
        {
            $tables[$fk['table_name']]->addManyToOne(new ManyToOne($fk['column_name'], $fk['foreign_column_name'], $fk['foreign_table_name']));
            $tables[$fk['foreign_table_name']]->addOneToMany(new OneToMany($fk['foreign_column_name'], $fk['column_name'], $fk['table_name']));
        }
    }

    /**
     * @param Table $table
     * @return string Name of the stored procedure
     */
    public function writeFindProcedure(Table $table)
    {
        $procedureName = 'find' . $table->getName();
        $alias         = $table->getName()[0];
        $pdo           = PDOS::getInstance();

        $proto     = '';
        $where     = '';
        $signature = '';
        $count     = 1;
        foreach ($table->getPrimaryKey()->getFields() as $field)
        {
            $proto .= $field->getName() . ' ' . $field->getType() . ', ';
            $where .= $alias . "." . $field->getName() . ' = $' . $count++ . ' AND ';
            $signature .= $field->getType() . ', ';
        }
        $proto     = substr($proto, 0, -2);
        $where     = substr($where, 0, -5);
        $signature = substr($signature, 0, -2);

        $pdo->exec("CREATE OR REPLACE function " . $procedureName . "(" . $proto . ")
        RETURNS " . $table->getName() . " AS
        'SELECT * FROM " . $table->getName() . " " . $alias . " WHERE " . $where . "'
        LANGUAGE sql VOLATILE
        COST 100;
        ALTER function " . $procedureName . "(" . $signature . ")
        OWNER TO \"" . DBUSER . "\";");

        return $procedureName;
    }

    /**
     * WARNING ! Foreign denomination is inverted compared with constraints query result
     *
     * @param string $foreignTable
     * @param string $foreignColumn
     * @param string $foreignColumnClean
     * @param string $tableName
     * @param string $columnType
     * @return string
     */
    public function writeOneToManyProcedure($foreignTable, $foreignColumn, $foreignColumnClean, $tableName, $columnType)
    {
        $procedureName = strtolower('otm_' . $foreignTable . 'from' . Tools::removeSFromTableName($tableName) . '_' . $foreignColumnClean);
        $pdo           = PDOS::getInstance();

        $pdo->exec("
        CREATE OR REPLACE function " . $procedureName . "(foreign_column " . $columnType . ")
        RETURNS SETOF " . $foreignTable . " AS
        'SELECT * FROM " . $foreignTable . " WHERE " . $foreignColumn . " = \$1'
        LANGUAGE sql VOLATILE
        COST 100
        ROWS 1000;
        ALTER function " . $procedureName . "(" . $columnType . ")
        OWNER TO \"" . DBUSER . "\";");

        return $procedureName;
    }

    /**
     * @param Table $table
     * @return string Name of the stored procedure
     */
    public function writeAllProcedure(Table $table)
    {
        $pdo           = PDOS::getInstance();
        $sTableName    = $table->getName();
        $procedureName = 'getall' . $sTableName;

        $pdo->exec("CREATE OR REPLACE function " . $procedureName . "()
          RETURNS SETOF " . $sTableName . " AS
          'SELECT * FROM " . $sTableName . "'
          LANGUAGE SQL VOLATILE
          COST 100;
          ALTER function " . $procedureName . "()
          OWNER TO \"" . DBUSER . "\";");

        return $procedureName;
    }

    /**
     * @param Table $table
     * @return string Name of the stored procedure
     */
    public function writeTakeProcedure(Table $table)
    {
        $sTableName    = $table->getName();
        $procedureName = 'take' . $sTableName;
        $pdo           = PDOS::getInstance();

        $pdo->exec("
        CREATE OR REPLACE FUNCTION $procedureName(start integer, \"number\" integer, order_ character varying)
          RETURNS SETOF $sTableName AS
        \$BODY\$BEGIN
          IF \$3 IS NOT NULL AND \$3 <> 'null' THEN
            RETURN QUERY EXECUTE 'SELECT * FROM $sTableName ORDER BY ' || $3 || ' OFFSET \$1 LIMIT \$2' USING \$1, \$2;
          ELSE
            RETURN QUERY EXECUTE 'SELECT * FROM $sTableName OFFSET \$1 LIMIT \$2' USING \$1, \$2;
          END IF;
        END
        \$BODY$
          LANGUAGE plpgsql VOLATILE
          COST 100
          ROWS 1000;
        ALTER FUNCTION $procedureName(integer, integer, character varying)
          OWNER TO \"" . DBUSER . "\";
        ");

        return $procedureName;
    }

    /**
     * @param Table $table
     * @return string Name of the stored procedure
     */
    public function writeCountProcedure(Table $table)
    {
        $pdo           = PDOS::getInstance();
        $sTableName    = $table->getName();
        $procedureName = 'count' . $sTableName;

        $pdo->exec("
          CREATE OR REPLACE function " . $procedureName . "()
          RETURNS bigint AS
          'SELECT COUNT(*) FROM " . $sTableName . "'
          LANGUAGE sql VOLATILE
          COST 100;
          ALTER function count" . $sTableName . "()
          OWNER TO \"" . DBUSER . "\";
          ");

        return $procedureName;
    }

    /**
     * @param \PDOStatement $statement
     * @param string        $sParamName
     * @param mixed         $paramValue
     * @return bool
     */
    public static function bindPDOValue(\PDOStatement &$statement, $sParamName, $paramValue)
    {
        if (is_bool($paramValue))
        {
            $iParamType = PDO::PARAM_BOOL;
            $paramValue = $paramValue ? 'true' : 'false';
        }
        else if (is_int($paramValue))
            $iParamType = PDO::PARAM_INT;
        else
            $iParamType = PDO::PARAM_STR;

        return $statement->bindValue($sParamName, $paramValue, $iParamType);
    }

    /**
     * @param Table[] $tables
     * @return StoredProcedure[]
     */
    private function readCustomStoredProcedures($tables)
    {
        $pdo = PDOS::getInstance();

        $query = $pdo->prepare("
        SELECT
          r.routine_name,
          r.type_udt_name AS routine_return_type,
          p.ordinal_position AS parameter_position,
          p.parameter_name,
          p.data_type AS parameter_type,
          p.parameter_mode
        FROM
          information_schema.routines r
          LEFT JOIN information_schema.parameters p ON p.specific_name = r.specific_name
        WHERE
          r.specific_schema = '" . DBSCHEMA . "' AND
          r.routine_type = 'FUNCTION' AND
          r.routine_name LIKE 'sp_%'
        ORDER BY
          r.routine_name, parameter_position
        ");

        $query->execute();

        $aRawStoredProcedures = array();
        foreach ($query->fetchAll() as $param)
        {
            if (!array_key_exists($param['routine_name'], $aRawStoredProcedures))
            {
                $aRawStoredProcedures[$param['routine_name']] = array(
                    'name'        => $param['routine_name'],
                    'return_type' => $param['routine_return_type'],
                    'parameters'  => array()
                );
            }
            if (!is_null($param['parameter_position']))
            {
                $aRawStoredProcedures[$param['routine_name']]['parameters'][] = new SPParameter($param['parameter_name'],
                    $param['parameter_type'], $param['parameter_mode'], $param['parameter_position']);
            }
        }

        /** @var StoredProcedure[] $aRawStoredProcedures */
        $aStoredProcedures = array();
        $SPFactory            = new StoredProcedureFactory($tables);
        foreach ($aRawStoredProcedures as $rawSP)
        {
            $aStoredProcedures[] = $SPFactory->getStoredProcedure($rawSP['name'], $rawSP['return_type'], $rawSP['parameters']);
        }

        return $aStoredProcedures;
    }

    /**
     * @param string $str
     * @return bool
     */
    public static function isTypeString($str)
    {
        $stringType = array(
            'character varying',
            'character',
            'text'
        );

        return in_array($str, $stringType);
    }
}
