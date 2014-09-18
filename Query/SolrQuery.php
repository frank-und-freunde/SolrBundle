<?php
namespace FS\SolrBundle\Query;

class SolrQuery extends AbstractQuery
{

    /**
     * @var array
     */
    private $mappedFields = array();

    /**
     * @var bool
     */
    private $useOnlyMappedFields = true;

    /**
     * @var array
     */
    private $searchTerms = array();

    /**
     * @var bool
     */
    private $useAndOperator = false;

    /**
     * @var bool
     */
    private $useWildcards = true;

    /**
     * @var string
     */
    private $customQuery = '';

    /**
     * @return array
     */
    public function getResult()
    {
        return $this->solr->query($this);
    }

    /**
     * @return array
     */
    public function getMappedFields()
    {
        return $this->mappedFields;
    }

    /**
     * @param array $mappedFields
     */
    public function setMappedFields($mappedFields)
    {
        $this->mappedFields = $mappedFields;
    }

    public function useOnlyMappedFields($useOnlyMappedFields)
    {
        $this->useOnlyMappedFields = $useOnlyMappedFields;
    }

    /**
     * @param bool $strict
     */
    public function setUseAndOperator($strict)
    {
        $this->useAndOperator = $strict;
    }

    /**
     * @param bool $boolean
     */
    public function setUseWildcard($boolean)
    {
        $this->useWildcards = $boolean;
    }

    /**
     * @return string
     */
    public function getCustomQuery()
    {
        return $this->customQuery;
    }

    /**
     * @param string $query
     */
    public function setCustomQuery($query)
    {
        $this->customQuery = $query;
    }

    /**
     * @return array
     */
    public function getSearchTerms()
    {
        return $this->searchTerms;
    }

    /**
     * @param array $value
     */
    public function queryAllFields($value)
    {
        $this->setUseAndOperator(false);

        foreach ($this->mappedFields as $documentField => $entityField) {
            $this->searchTerms[$documentField] = $value;
        }
    }

    /**
     *
     * @param string $field
     * @param string $value
     * @throws \InvalidArgumentException
     * @return SolrQuery
     */
    public function addSearchTerm($field, $value)
    {
        $documentFieldsAsValues = array_flip($this->mappedFields);

        if (array_key_exists($field, $documentFieldsAsValues)) {
            $documentFieldName = $documentFieldsAsValues[$field];
            $this->searchTerms[$documentFieldName] = $value;
        } elseif ($this->useOnlyMappedFields) {
            $errorMessage =
                'Field "%s" is not mapped on "%s", available fields are %s. '.
                'You can set the option "useOnlyMappedFields" to false to skip this check.'
            ;

            throw new \InvalidArgumentException(sprintf($errorMessage,
                $field,
                is_object($this->getEntity()) ? get_class($this->getEntity()) : $this->getEntity(),
                implode(', ', $documentFieldsAsValues)
            ));
        }

        return $this;
    }

    /**
     * @param string $field
     * @return SolrQuery
     */
    public function addField($field)
    {
        $entityFieldNames = array_flip($this->mappedFields);
        if (array_key_exists($field, $entityFieldNames)) {
            parent::addField($entityFieldNames[$field]);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        if ($this->customQuery) {
            $this->setQuery($this->customQuery);
            return $this->customQuery;
        }

        $term = '';
        if (count($this->searchTerms) == 0) {
            return $term;
        }

        $logicOperator = 'AND';
        if (!$this->useAndOperator) {
            $logicOperator = 'OR';
        }

        $termCount = 1;
        foreach ($this->searchTerms as $fieldName => $fieldValue) {

            if ($this->useWildcards) {
                $term .= $fieldName . ':*' . $fieldValue . '*';
            } else {
                $term .= $fieldName . ':' . $fieldValue;
            }

            if ($termCount < count($this->searchTerms)) {
                $term .= ' ' . $logicOperator . ' ';
            }

            $termCount++;
        }

        $this->setQuery($term);

        return $term;
    }

}
