<?php
namespace FS\SolrBundle\Doctrine\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
class Field extends Annotation
{

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $name;

    /**
     * @var numeric
     */
    public $boost = 0;

    /**
     * @var bool
     */
    public $dynamic = true;

    /**
     * @var bool
     */
    public $multiValued = false;

    /**
     * @var array
     */
    private static $TYP_MAPPING = array(
        'string' => 's',
        'text' => 't',
        'date' => 'dt',
        'boolean' => 'b',
        'integer' => 'i',
        'long' => 'l',
        'float' => 'f',
        'double' => 'd',
        'location' => 'co'
    );

    /**
     * returns field name with type-suffix:
     *
     * eg: title_s
     *
     * @throws \RuntimeException
     * @return string
     */
    public function getNameWithAlias()
    {
        return $this->normalizeName($this->name) . $this->getTypeSuffix($this->type);
    }

    /**
     * @param string $type
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @return string
     */
    private function getTypeSuffix($type)
    {
        $suffix = '';

        if ($this->dynamic) {
            // add separator
            $suffix .= '_';

            // add type alias
            if (!array_key_exists($this->type, self::$TYP_MAPPING)) {
                throw new \InvalidArgumentException(sprintf('Unknown type "%s", supported types: %s',
                    $this->type,
                    implode(', ', array_keys(self::$TYP_MAPPING))
                ));
            }

            $suffix .= self::$TYP_MAPPING[$this->type];

            // append m for multiValued
            if ($this->multiValued) {
                $suffix .= 'm';
            }
        } else {
            if ($this->multiValued) {
                throw new \LogicException('You can use "multiValued" only for dynamic fields.'.
                    'Multi valued Non-dynamic fields have to be configured in your schema.xml');
            }
        }

        return $suffix;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * @throws \InvalidArgumentException if boost is not a number
     * @return number
     */
    public function getBoost()
    {
        if (!is_numeric($this->boost)) {
            throw new \InvalidArgumentException(sprintf('Invalid boost value %s', $this->boost));
        }

        if (($boost = floatval($this->boost)) > 0) {
            return $boost;
        }

        return null;
    }

    /**
     * normalize class attributes camelcased names to underscores
     * (according to solr specification, document field names should
     * contain only lowercase characters and underscores to maintain
     * retro compatibility with old components).
     *
     * @param $name The field name
     *
     * @return string normalized field name
     */
    private function normalizeName($name)
    {
        $words = preg_split('/(?=[A-Z])/', $name);
        $words = array_map(
            function ($value) {
                return strtolower($value);
            },
            $words
        );

        return implode('_', $words);
    }
}
