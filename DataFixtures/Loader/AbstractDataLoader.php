<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\DataFixtures\Loader;

use Propel\Bundle\PropelBundle\DataFixtures\AbstractDataHandler;
use Propel\Bundle\PropelBundle\Util\PropelInflector;
use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\Exception\TableNotFoundException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;

/**
 * Abstract class to manage a common logic to load datas.
 *
 * @author William Durand <william.durand1@gmail.com>
 */
abstract class AbstractDataLoader extends AbstractDataHandler implements DataLoaderInterface
{
    /** @var string[] */
    protected array $deletedClasses = array();

    /** @var array<string, ActiveRecordInterface> */
    protected array $object_references = array();

    /**
     * Transforms a file containing data in an array.
     *
     * @param string $file A filename.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    abstract protected function transformDataToArray(string $file): array;

    /**
     * {@inheritdoc}
     */
    public function load(array $files, string $connectionName): int
    {
        $nbFiles = 0;
        $this->deletedClasses = array();

        $this->loadMapBuilders($connectionName);
        $this->con = Propel::getConnection($connectionName);

        try {
            $this->con->beginTransaction();

            $datas = array();
            foreach ($files as $file) {
                $content = $this->transformDataToArray($file);

                if (count($content) > 0) {
                    $datas = array_merge_recursive($datas, $content);
                    $nbFiles++;
                }
            }

            $this->deleteCurrentData($datas);
            $this->loadDataFromArray($datas);

            $this->con->commit();
        } catch (\Exception $e) {
            $this->con->rollBack();
            throw $e;
        }

        return $nbFiles;
    }

    /**
     * Deletes current data.
     *
     * @param array<string, array<string, array<string, mixed>>>|null $data The data to delete
     *
     * @throws \ReflectionException
     */
    protected function deleteCurrentData(?array $data = null): void
    {
        if ($data !== null) {
            $classes = array_keys($data);
            foreach (array_reverse($classes) as $class) {
                $class = trim($class);
                if (in_array($class, $this->deletedClasses)) {
                    continue;
                }
                $this->deleteClassData($class);
            }
        }
    }

    /**
     * Delete data for a given class, and for its ancestors (if any).
     *
     * @param string $class Class name to delete
     *
     * @throws \ReflectionException
     */
    protected function deleteClassData(string $class): void
    {
        $tableMap = $this->dbMap->getTable(constant(constant($class.'::TABLE_MAP').'::TABLE_NAME'));
        $tableMap->doDeleteAll($this->con);

        $this->deletedClasses[] = $class;

        // Remove ancestors data
        if (false !== ($parentClass = get_parent_class(get_parent_class($class)))) {
            $reflectionClass = new \ReflectionClass($parentClass);
            if (!$reflectionClass->isAbstract()) {
                $this->deleteClassData($parentClass);
            }
        }
    }

    /**
     * Loads the data using the generated data model.
     *
     * @param array<string, array<string, array<string, mixed>>>|null $data The data to be loaded
     *
     * @throws \ReflectionException
     */
    protected function loadDataFromArray(?array $data = null): void
    {
        if ($data === null) {
            return;
        }

        foreach ($data as $class => $datas) {
            // iterate through datas for this class
            // might have been empty just for force a table to be emptied on import
            if (!is_array($datas)) {
                continue;
            }

            $class = trim($class);
            if ('\\' == $class[0]) {
                $class = substr($class, 1);
            }
            $tableMap     = $this->dbMap->getTable(constant(constant($class.'::TABLE_MAP').'::TABLE_NAME'));
            $column_names = $tableMap->getFieldnames(TableMap::TYPE_PHPNAME);

            foreach ($datas as $key => $values) {
                // create a new entry in the database
                if (!class_exists($class)) {
                    throw new \InvalidArgumentException(sprintf('Unknown class "%s".', $class));
                }

                $obj = new $class();

                if (!$obj instanceof ActiveRecordInterface) {
                    throw new \RuntimeException(
                        sprintf('The class "%s" is not a Propel class. There is probably another class named "%s" somewhere.', $class, $class)
                    );
                }

                if (!is_array($values)) {
                    throw new \InvalidArgumentException(sprintf('You must give a name for each fixture data entry (class %s).', $class));
                }

                foreach ($values as $name => $value) {
                    if (is_array($value) && 's' === substr($name, -1)) {
                        try {
                            // many to many relationship
                            $this->loadManyToMany($obj, substr($name, 0, -1), $value);

                            continue;
                        } catch (TableNotFoundException $e) {
                            // Check whether this is actually an array stored in the object.
                            if ('Cannot fetch TableMap for undefined table: ' . substr($name, 0, -1) === $e->getMessage()) {
                                if (PropelTypes::PHP_ARRAY !== $tableMap->getColumn($name)->getType()
                                    && PropelTypes::OBJECT !== $tableMap->getColumn($name)->getType()) {
                                    throw $e;
                                }
                            }
                        }
                    }

                    if ($tableMap->hasColumn($name)) {
                        $column = $tableMap->getColumn($name);
                    } elseif ($tableMap->hasColumnByPhpName($name)) {
                        $column = $tableMap->getColumnByPhpName($name);
                    } else {
                        $column = null;
                    }

                    // foreign key?
                    if (null !== $column) {
                        /*
                         * A column, which is a PrimaryKey (self referencing, e.g. versionable behavior),
                         * but which is not a ForeignKey (e.g. delegatable behavior on 1:1 relation).
                         */
                        if ($column->isPrimaryKey() && null !== $value && !$column->isForeignKey()) {
                            if (isset($this->object_references[$this->cleanObjectRef($class.'_'.$value)])) {
                                $obj = $this->object_references[$this->cleanObjectRef($class.'_'.$value)];

                                continue;
                            }
                        }

                        if ($column->isForeignKey() && null !== $value) {
                            $relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
                            if (isset($this->object_references[$this->cleanObjectRef($relatedTable->getClassname().'_'.$value)])) {
                                $value = $this
                                    ->object_references[$this->cleanObjectRef($relatedTable->getClassname().'_'.$value)]
                                    ->getByName($column->getRelatedName(), TableMap::TYPE_COLNAME);
                            } else {
                                $relatedClass = $this->cleanObjectRef($relatedTable->getClassName());
                                if (isset($data[$relatedClass]) || isset($data['\\' . $relatedClass])) {
                                    throw new \InvalidArgumentException(
                                        sprintf('The object "%s" from class "%s" is not defined in your data file.', $value, $relatedTable->getClassname())
                                    );

                                }
                            }
                        }
                    }

                    if (false !== $pos = array_search($name, $column_names)) {
                        $obj->setByPosition($pos, $value);
                    } elseif (is_callable(array($obj, $method = 'set'.ucfirst(PropelInflector::camelize($name))))) {
                        $obj->$method($value);
                    } else {
                        throw new \InvalidArgumentException(sprintf('Column "%s" does not exist for class "%s".', $name, $class));
                    }
                }

                $obj->save($this->con);

                $this->saveParentReference($class, $key, $obj);
            }
        }
    }

    /**
     * Save a reference to the specified object (and its ancestors) before loading them.
     *
     * @param string                $class Class name of passed object
     * @param string                $key   Key identifying specified object
     * @param ActiveRecordInterface $obj   A Propel object
     *
     * @throws \ReflectionException
     */
    protected function saveParentReference(string $class, string $key, ActiveRecordInterface $obj): void
    {
        if (!method_exists($obj, 'getPrimaryKey')) {
            return;
        }

        $this->object_references[$this->cleanObjectRef($class.'_'.$key)] = $obj;

        // Get parent (schema ancestor) of parent (Propel base class) in case of inheritance
        if (false !== ($parentClass = get_parent_class(get_parent_class($class)))) {

            $reflectionClass = new \ReflectionClass($parentClass);
            if (!$reflectionClass->isAbstract()) {
                $parentObj = new $parentClass();
                $parentObj->fromArray($obj->toArray());
                $this->saveParentReference($parentClass, $key, $parentObj);
            }
        }
    }

    /**
     * Loads many to many objects.
     *
     * @param ActiveRecordInterface $obj             A Propel object
     * @param string                $middleTableName The middle table name
     * @param string[]              $values          An array of values
     */
    protected function loadManyToMany(ActiveRecordInterface $obj, string $middleTableName, array $values): void
    {
        $middleTable = $this->dbMap->getTable($middleTableName);
        $middleClass = $middleTable->getClassname();
        $tableName   = constant(constant(get_class($obj).'::TABLE_MAP').'::TABLE_NAME');

        foreach ($middleTable->getColumns() as $column) {
            if ($column->isForeignKey()) {
                if ($tableName !== $column->getRelatedTableName()) {
                    $relatedClass  = $this->dbMap->getTable($column->getRelatedTableName())->getClassname();
                    $relatedSetter = 'set' . $column->getRelation()->getName();
                } else {
                    $setter = 'set' . $column->getRelation()->getName();
                }
            }
        }

        if (!isset($relatedClass)) {
            throw new \InvalidArgumentException(sprintf('Unable to find the many-to-many relationship for object "%s".', get_class($obj)));
        }

        foreach ($values as $value) {
            if (!isset($this->object_references[$this->cleanObjectRef($relatedClass.'_'.$value)])) {
                throw new \InvalidArgumentException(
                    sprintf('The object "%s" from class "%s" is not defined in your data file.', $value, $relatedClass)
                );
            }

            $middle = new $middleClass();
            $middle->$setter($obj);
            $middle->$relatedSetter($this->object_references[$this->cleanObjectRef($relatedClass.'_'.$value)]);
            $middle->save($this->con);
        }
    }

    protected function cleanObjectRef(string $ref): string
    {
        return $ref[0] === '\\' ? substr($ref, 1) : $ref;
    }
}
