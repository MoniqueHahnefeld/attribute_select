<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package     MetaModels
 * @subpackage  AttributeSelect
 * @author      Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author      Christian de la Haye <service@delahaye.de>
 * @copyright   The MetaModels team.
 * @license     LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Select;

/**
 * This is the MetaModelAttribute class for handling select attributes on plain SQL tables.
 *
 * @package    MetaModels
 * @subpackage AttributeSelect
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian de la Haye <service@delahaye.de>
 */
class Select extends AbstractSelect
{
    /**
     * {@inheritdoc}
     */
    public function sortIds($arrIds, $strDirection)
    {
        $strTableName  = $this->getSelectSource();
        $strColNameId  = $this->get('select_id');
        $strSortColumn = $this->getSortingColumn();
        $arrIds        = $this->getDatabase()
            ->prepare(
                sprintf(
                    'SELECT %1$s.id FROM %1$s
                    LEFT JOIN %3$s ON (%3$s.%4$s=%1$s.%2$s)
                    WHERE %1$s.id IN (%5$s)
                    ORDER BY %3$s.%6$s %7$s',
                    // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                    $this->getMetaModel()->getTableName(), // 1
                    $this->getColName(),                   // 2
                    $strTableName,                         // 3
                    $strColNameId,                         // 4
                    implode(',', $arrIds),                 // 5
                    $strSortColumn,                        // 6
                    $strDirection                          // 7
                    // @codingStandardsIgnoreEnd
                )
            )
            ->execute()
            ->fetchEach('id');
        return $arrIds;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeSettingNames()
    {
        return array_merge(parent::getAttributeSettingNames(), array(
            'select_id',
            'select_alias',
            'select_where',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function valueToWidget($varValue)
    {
        $strColNameAlias = $this->get('select_alias');
        if ($this->isTreePicker() || !$strColNameAlias) {
            $strColNameAlias = $this->get('select_id');
        }

        return $varValue[$strColNameAlias];
    }

    /**
     * {@inheritdoc}
     */
    public function widgetToValue($varValue, $intId)
    {
        $database        = $this->getDatabase();
        $strColNameAlias = $this->get('select_alias');
        $strColNameId    = $this->get('select_id');
        if ($this->isTreePicker() || !$strColNameAlias) {
            $strColNameAlias = $strColNameId;
        }
        // Lookup the id for this value.
        $objValue = $database
            ->prepare(sprintf('SELECT %1$s.* FROM %1$s WHERE %2$s=?', $this->get('select_table'), $strColNameAlias))
            ->execute($varValue);

        return $objValue->row();
    }

    /**
     * Convert a native attribute value into a value to be used in a filter Url.
     *
     * This returns the value of the alias if any defined or the value of the id otherwise.
     *
     * @param mixed $varValue The source value.
     *
     * @return string
     */
    public function getFilterUrlValue($varValue)
    {
        return urlencode($varValue[$this->getAliasColumn()]);
    }

    /**
     * Determine the correct alias column to use.
     *
     * @return string
     */
    protected function getAliasColumn()
    {
        return $this->get('select_alias') ?: $this->get('select_id');
    }

    /**
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getAdditionalWhere()
    {
        return $this->get('select_where') ? html_entity_decode($this->get('select_where')) : false;
    }

    /**
     * Convert the database result into a proper result array.
     *
     * @param \Database\Result $values      The database result.
     *
     * @param string           $aliasColumn The name of the alias column to be used.
     *
     * @param string           $valueColumn The name of the value column.
     *
     * @param array            $count       The optional count array.
     *
     * @return array
     */
    protected function convertOptionsList($values, $aliasColumn, $valueColumn, &$count = null)
    {
        $arrReturn = array();
        while ($values->next()) {
            if (is_array($count)) {
                $count[$values->$aliasColumn] = $values->mm_count;
            }

            $arrReturn[$values->$aliasColumn] = $values->$valueColumn;
        }

        return $arrReturn;
    }

    /**
     * Fetch filter options from foreign table taking the given flag into account.
     *
     * @param bool $usedOnly The flag if only used values shall be returned.
     *
     * @return \Database\Result
     */
    public function getFilterOptionsForUsedOnly($usedOnly)
    {
        $additionalWhere = $this->getAdditionalWhere();
        $sortColumn      = $this->getSortingColumn();
        if ($usedOnly) {
            return \Database::getInstance()->executeUncached(sprintf(
                'SELECT COUNT(%1$s.%2$s) as mm_count, %1$s.*
                    FROM %1$s
                    RIGHT JOIN %3$s ON (%3$s.%4$s=%1$s.%2$s)
                    %5$s
                    GROUP BY %1$s.%2$s
                    ORDER BY %1$s.%6$s',
                // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                $this->getSelectSource(),                                  // 1
                $this->get('select_id'),                                   // 2
                $this->getMetaModel()->getTableName(),                     // 3
                $this->getColName(),                                       // 4
                ($additionalWhere ? ' WHERE ('.$additionalWhere.')' : ''), // 5
                $sortColumn                                                // 6
                // @codingStandardsIgnoreEnd
            ));
        }

        return \Database::getInstance()->executeUncached(sprintf(
            'SELECT COUNT(%3$s.%4$s) as mm_count, %1$s.*
                FROM %1$s
                LEFT JOIN %3$s ON (%3$s.%4$s=%1$s.%2$s)
                %5$s
                GROUP BY %1$s.%2$s
                ORDER BY %1$s.%6$s',
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
            $this->getSelectSource(),                                  // 1
            $this->get('select_id'),                                   // 2
            $this->getMetaModel()->getTableName(),                     // 3
            $this->getColName(),                                       // 4
            ($additionalWhere ? ' WHERE ('.$additionalWhere.')' : ''), // 5
            $sortColumn                                                // 6
            // @codingStandardsIgnoreEnd
        ));
    }

    /**
     * {@inheritdoc}
     *
     * Fetch filter options from foreign table.
     *
     */
    public function getFilterOptions($arrIds, $usedOnly, &$arrCount = null)
    {
        if (($arrIds !== null) && empty($arrIds)) {
            return array();
        }

        $tableName = $this->getSelectSource();
        $idColumn  = $this->get('select_id');

        if (empty($tableName) || empty($idColumn)) {
            return array();
        }

        $strSortColumn   = $this->getSortingColumn();
        $strColNameWhere = $this->getAdditionalWhere();

        $objDB = \Database::getInstance();
        if ($arrIds) {
            $objValue = $objDB
                ->prepare(sprintf(
                    'SELECT COUNT(%1$s.%2$s) as mm_count, %1$s.*
                    FROM %1$s
                    RIGHT JOIN %3$s ON (%3$s.%4$s=%1$s.%2$s)
                    WHERE (%3$s.id IN (%5$s)%6$s)
                    GROUP BY %1$s.%2$s
                    ORDER BY %1$s.%7$s',
                    // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                    $tableName,                                              // 1
                    $idColumn,                                               // 2
                    $this->getMetaModel()->getTableName(),                   // 3
                    $this->getColName(),                                     // 4
                    implode(',', $arrIds),                                   // 5
                    ($strColNameWhere ? ' AND ('.$strColNameWhere.')' : ''), // 6
                    $strSortColumn                                           // 7
                    // @codingStandardsIgnoreEnd
                ))
                ->execute($this->get('id'));
        } else {
            $objValue = $this->getFilterOptionsForUsedOnly($usedOnly);
        }

        return $this->convertOptionsList($objValue, $this->getAliasColumn(), $this->getValueColumn(), $arrCount);
    }

    /**
     * {@inheritdoc}
     */
    public function getDataFor($arrIds)
    {
        $objDB          = \Database::getInstance();
        $strTableNameId = $this->getSelectSource();
        $strColNameId   = $this->get('select_id');
        $arrReturn      = array();

        if ($strTableNameId && $strColNameId) {
            $strMetaModelTableName   = $this->getMetaModel()->getTableName();
            $strMetaModelTableNameId = $strMetaModelTableName.'_id';

            // Using aliased join here to resolve issue #3 - SQL error for self referencing table.
            $objValue = $objDB
                ->prepare(sprintf(
                    'SELECT sourceTable.*, %2$s.id AS %3$s
                    FROM %1$s sourceTable
                    LEFT JOIN %2$s ON (sourceTable.%4$s=%2$s.%5$s)
                    WHERE %2$s.id IN (%6$s)',
                    // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                    $strTableNameId,          // 1
                    $strMetaModelTableName,   // 2
                    $strMetaModelTableNameId, // 3
                    $strColNameId,            // 4
                    $this->getColName(),      // 5
                    implode(',', $arrIds)     // 6
                    // @codingStandardsIgnoreEnd
                ))
                ->execute();

            while ($objValue->next()) {
                $arrReturn[$objValue->$strMetaModelTableNameId] = $objValue->row();
            }
        }
        return $arrReturn;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataFor($arrValues)
    {
        $strTableName = $this->getSelectSource();
        $strColNameId = $this->get('select_id');
        if ($strTableName && $strColNameId) {
            $strQuery = sprintf(
                'UPDATE %1$s SET %2$s=? WHERE %1$s.id=?',
                $this->getMetaModel()->getTableName(),
                $this->getColName()
            );

            $objDB = \Database::getInstance();
            foreach ($arrValues as $intItemId => $arrValue) {
                $objDB->prepare($strQuery)->execute($arrValue[$strColNameId], $intItemId);
            }
        }
    }
}
