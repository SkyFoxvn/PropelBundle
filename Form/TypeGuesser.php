<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Bundle\PropelBundle\Form;

use Propel\Bundle\PropelBundle\Form\Type\ModelType;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\RelationMap;
use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

/**
 * Propel Type guesser.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TypeGuesser implements FormTypeGuesserInterface
{
    /** @var array<string, TableMap|ColumnMap> */
    private array $cache = array();

    /**
     * {@inheritDoc}
     *
     * @param string $class
     * @param string $property
     */
    public function guessType($class, $property): ?TypeGuess
    {
        if (!$table = $this->getTable($class)) {
            return new TypeGuess(TextType::class, array(), Guess::LOW_CONFIDENCE);
        }

        foreach ($table->getRelations() as $relation) {
            if ($relation->getType() === RelationMap::MANY_TO_ONE) {
                if (strtolower($property) === strtolower($relation->getName())) {
                    return new TypeGuess(ModelType::class, array(
                        'class'    => $relation->getForeignTable()->getClassName(),
                        'multiple' => false,
                    ), Guess::HIGH_CONFIDENCE);
                }
            } elseif ($relation->getType() === RelationMap::ONE_TO_MANY) {
                if (strtolower($property) === strtolower($relation->getPluralName())) {
                    return new TypeGuess(ModelType::class, array(
                        'class'    => $relation->getForeignTable()->getClassName(),
                        'multiple' => true,
                    ), Guess::HIGH_CONFIDENCE);
                }
            } elseif ($relation->getType() === RelationMap::MANY_TO_MANY) {
                if (strtolower($property) == strtolower($relation->getPluralName())) {
                    return new TypeGuess(ModelType::class, array(
                        'class'    => $relation->getLocalTable()->getClassName(),
                        'multiple' => true,
                    ), Guess::HIGH_CONFIDENCE);
                }
            }
        }

        if (!$column = $this->getColumn($class, $property)) {
            return new TypeGuess(TextType::class, array(), Guess::LOW_CONFIDENCE);
        }

        switch ($column->getType()) {
            case PropelTypes::BOOLEAN:
            case PropelTypes::BOOLEAN_EMU:
                return new TypeGuess(CheckboxType::class, array(), Guess::HIGH_CONFIDENCE);
            case PropelTypes::TIMESTAMP:
            case PropelTypes::BU_TIMESTAMP:
                return new TypeGuess(DateTimeType::class, array(), Guess::HIGH_CONFIDENCE);
            case PropelTypes::DATE:
            case PropelTypes::BU_DATE:
                return new TypeGuess(DateType::class, array(), Guess::HIGH_CONFIDENCE);
            case PropelTypes::TIME:
                return new TypeGuess(TimeType::class, array(), Guess::HIGH_CONFIDENCE);
            case PropelTypes::FLOAT:
            case PropelTypes::REAL:
            case PropelTypes::DOUBLE:
            case PropelTypes::DECIMAL:
                return new TypeGuess(NumberType::class, array(), Guess::MEDIUM_CONFIDENCE);
            case PropelTypes::TINYINT:
            case PropelTypes::SMALLINT:
            case PropelTypes::INTEGER:
            case PropelTypes::BIGINT:
            case PropelTypes::NUMERIC:
                return new TypeGuess(IntegerType::class, array(), Guess::MEDIUM_CONFIDENCE);
            case PropelTypes::ENUM:
            case PropelTypes::CHAR:
                if ($column->getValueSet()) {
                    //check if this is mysql enum
                    $choices = $column->getValueSet();
                    $labels = array_map('ucfirst', $choices);

                    return new TypeGuess(ChoiceType::class, array('choices' => array_combine($choices, $labels)), Guess::MEDIUM_CONFIDENCE);
                }
            case PropelTypes::VARCHAR:
                return new TypeGuess(TextType::class, array(), Guess::MEDIUM_CONFIDENCE);
            case PropelTypes::LONGVARCHAR:
            case PropelTypes::BLOB:
            case PropelTypes::CLOB:
            case PropelTypes::CLOB_EMU:
                return new TypeGuess(TextareaType::class, array(), Guess::MEDIUM_CONFIDENCE);
            default:
                return new TypeGuess(TextType::class, array(), Guess::LOW_CONFIDENCE);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     * @param string $property
     */
    public function guessRequired($class, $property): ?ValueGuess
    {
        if ($column = $this->getColumn($class, $property)) {
            return new ValueGuess($column->isNotNull(), Guess::HIGH_CONFIDENCE);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     * @param string $property
     */
    public function guessMaxLength($class, $property): ?ValueGuess
    {
        if ($column = $this->getColumn($class, $property)) {
            if ($column->isText()) {
                return new ValueGuess($column->getSize(), Guess::HIGH_CONFIDENCE);
            }
            switch ($column->getType()) {
                case PropelTypes::FLOAT:
                case PropelTypes::REAL:
                case PropelTypes::DOUBLE:
                case PropelTypes::DECIMAL:
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     * @param string $property
     */
    public function guessPattern($class, $property): ?ValueGuess
    {
        if ($column = $this->getColumn($class, $property)) {
            switch ($column->getType()) {
                case PropelTypes::FLOAT:
                case PropelTypes::REAL:
                case PropelTypes::DOUBLE:
                case PropelTypes::DECIMAL:
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }

        return null;
    }

    /**
     * @param string $class
     *
     * @return TableMap|null
     */
    protected function getTable(string $class)
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        if (class_exists($queryClass = $class.'Query')) {
            $query = new $queryClass();

            return $this->cache[$class] = $query->getTableMap();
        }
        
        return null;
    }

    /**
     * @param string $class
     * @param string $property
     *
     * @return ColumnMap|null
     */
    protected function getColumn(string $class, string $property)
    {
        if (isset($this->cache[$class.'::'.$property])) {
            return $this->cache[$class.'::'.$property];
        }

        $table = $this->getTable($class);

        if ($table && $table->hasColumn($property)) {
            return $this->cache[$class.'::'.$property] = $table->getColumn($property);
        }

        return null;
    }
}
