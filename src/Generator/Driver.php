<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mathieu.savy
 * Date: 21/08/13
 * Time: 11:46
 * To change this template use File | Settings | File Templates.
 */

namespace MicroMuffin\Generator;

abstract class Driver implements IParamBindable, IDriveHasTypeString
{
    /** @var AbstractSchema */
    protected $abstractSchema;

    protected abstract function readDatabaseSchema();

    public abstract function writeFindProcedure(Table $table);

    public abstract function writeOneToManyProcedure($foreignTable, $foreignColumn, $foreignColumnClean, $tableName, $columnType);

    public abstract function writeAllProcedure(Table $table);

    public abstract function writeCountProcedure(Table $table);

    public abstract function writeTakeProcedure(Table $table);

    /**
     * @return AbstractSchema
     */
    public function getAbstractSchema()
    {
        $this->readDatabaseSchema();

        return $this->abstractSchema;
    }
}