<?php
namespace FS\SolrBundle\Doctrine\Annotation;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader as Reader;

class AnnotationReader
{
    /**
     * @var array
     */
    protected static $inMemoryCache = [
        'reflection_class' => [],
        'fields' => [],
    ];

    /**
     * @var Reader
     */
    private $reader;

    const DOCUMENT_CLASS = 'FS\SolrBundle\Doctrine\Annotation\Document';
    const FIELD_CLASS = 'FS\SolrBundle\Doctrine\Annotation\Field';
    const FIELD_IDENTIFIER_CLASS = 'FS\SolrBundle\Doctrine\Annotation\Id';
    const DOCUMENT_INDEX_CLASS = 'FS\SolrBundle\Doctrine\Annotation\Document';
    const SYNCHRONIZATION_FILTER_CLASS = 'FS\SolrBundle\Doctrine\Annotation\SynchronizationFilter';

    public function __construct()
    {
        // never call it directly!
        $this->reader = new Reader();
    }

    /**
     * reads the entity and returns a set of annotations
     *
     * @param string $entity
     * @param string $type
     * @return array
     */
    private function getPropertiesByType($entity, $type)
    {
        $reflectionClass = static::createOrGetReflectionClass($entity);
        $properties = $reflectionClass->getProperties();

        $fields = array();
        foreach ($properties as $property) {
            /* @var \ReflectionProperty $property */
            /* @var \FS\SolrBundle\Doctrine\Annotation\Field $annotation */
            $annotation = $this->getPropertyAnnotation($property, $type);

            if (null === $annotation) {
                continue;
            }

            $property->setAccessible(true);

            $annotation->name = $property->getName();
            // todo
            if (is_object($entity)) {
                $annotation->value = $property->getValue($entity);
            } else {
                $annotation->value = null;
            }

            $fields[] = $annotation;
        }

        return $fields;
    }

    /**
     * reads the entity and returns a set of annotations
     *
     * @param string $entity
     * @param string $type
     * @throws AnnotationException
     * @return array
     */
    private function getMethodsByType($entity, $type)
    {
        $reflectionClass = static::createOrGetReflectionClass($entity);
        $methods = $reflectionClass->getMethods();

        $fields = array();
        foreach ($methods as $method) {
            /* @var \ReflectionMethod $method */
            /* @var \FS\SolrBundle\Doctrine\Annotation\Field $annotation */
            $annotation = $this->getMethodAnnotation($method, $type);

            if (null === $annotation) {
                continue;
            }

            if (!$method->isPublic()) {
                throw new AnnotationException(sprintf('Method "%s" in class "%s" is not callable. Change visibility from %s to %s.',
                        $method->getName(),
                        $method->getDeclaringClass(),
                        $method->isPrivate() ? 'private' : 'protected'
                    ));
            }

            // todo
            if (is_object($entity)) {
                $annotation->value = $method->invoke($entity);
            } else {
                $annotation->value = null;
            }

            if ($annotation->name == '') {
                $annotation->name = $method->getName();
            }

            $fields[] = $annotation;
        }

        return $fields;
    }

    /**
     * @param object|string $entity
     * @return array
     */
    public function getFields($entity)
    {
        $key = null;
        if (!is_object($entity)) {
            $key = 'fields_'.$entity;
        }

        $fields = null;
        if (is_object($entity) || !$this->cacheHas($key)) {
            $fields = array_merge(
                $this->getPropertiesByType($entity, self::FIELD_CLASS),
                $this->getMethodsByType($entity, self::FIELD_CLASS)
            );
        }

        if (!is_object($entity)) {
            if ($fields != null) {
                static::$inMemoryCache['fields'][$key] = $fields;
            }

            $fields = static::$inMemoryCache['fields'][$key];
        }

        return $fields;
    }

    /**
     * @param object $entity
     * @throws \InvalidArgumentException if the boost value is not numeric
     * @return number
     */
    public function getEntityBoost($entity)
    {
        $class = static::createOrGetReflectionClass($entity);
        $annotation = $this->getClassAnnotation($class, self::DOCUMENT_INDEX_CLASS);

        if (!$annotation instanceof Document) {
            return 0;
        }

        try {
            $boostValue = $annotation->getBoost();
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf($e->getMessage() . ' for entity %s', get_class($entity)));
        }

        return $boostValue;
    }

    /**
     * @param object $entity
     * @return Type
     * @throws \RuntimeException
     */
    public function getIdentifier($entity)
    {
        $id = $this->getPropertiesByType($entity, self::FIELD_IDENTIFIER_CLASS);

        if (count($id) == 0) {
            throw new \RuntimeException('no identifer declared in class/entity ' . (is_object($entity) ? get_class($entity) : $entity));
        }

        return reset($id);
    }

    /**
     * @param object|string $entity
     * @return string classname of repository
     */
    public function getRepository($entity)
    {
        $class = static::createOrGetReflectionClass($entity);
        $annotation = $this->getClassAnnotation($class, self::DOCUMENT_CLASS);

        if ($annotation instanceof Document) {
            return $annotation->repository;
        }

        return '';
    }

    /**
     * returns all fields and field for idendification
     *
     * @param object|string $entity
     * @return array
     */
    public function getFieldMapping($entity)
    {
        $fields = $this->getFields($entity);

        $mapping = array();
        foreach ($fields as $field) {
            if ($field instanceof Field) {
                $mapping[$field->getNameWithAlias()] = $field->name;
            }
        }

        $id = $this->getIdentifier($entity);
        $mapping['id'] = $id->name;

        return $mapping;
    }

    /**
     * @param object $entity
     * @return boolean
     */
    public function hasDocumentDeclaration($entity)
    {
        $class = static::createOrGetReflectionClass($entity);
        $annotation = $this->getClassAnnotation($class, self::DOCUMENT_INDEX_CLASS);

        return $annotation !== null;
    }

    /**
     * @param string $entity
     * @return string
     */
    public function getSynchronizationCallback($entity)
    {
        $class = static::createOrGetReflectionClass($entity);
        $annotation = $this->getClassAnnotation($class, self::SYNCHRONIZATION_FILTER_CLASS);

        if (!$annotation) {
            return '';
        }

        return $annotation->callback;
    }

    /**
     * @param $entity
     * @return array
     */
    public function getDistriminatorMap($entity)
    {
        $class = static::createOrGetReflectionClass($entity);

        // todo: added support for orm too
        $odmDistriminatorMapClass = '\Doctrine\ODM\MongoDB\Mapping\Annotations\DiscriminatorMap';
        $odmDistriminatorMap = $this->getClassAnnotation($class, $odmDistriminatorMapClass);

        return $odmDistriminatorMap->value;
    }

    /**
     * @param $class
     * @return mixed
     */
    protected static function createOrGetReflectionClass($class)
    {
        if (is_object($class)) {
            return new \ReflectionClass($class);
        }

        if (!isset(static::$inMemoryCache['reflection_class'][$class])) {
            static::$inMemoryCache['reflection_class'][$class] = new \ReflectionClass($class);
        }

        return static::$inMemoryCache['reflection_class'][$class];
    }

    /**
     * @param \ReflectionClass $class
     * @return mixed
     */
    public function getClassAnnotations(\ReflectionClass $class)
    {
        $cacheKey = $class->getName().'_class_annotations';
        if (!$this->cacheHas($cacheKey)) {
            $this->cacheAdd($cacheKey, $this->reader->getClassAnnotations($class));
        }
        return $this->cacheGet($cacheKey);
    }

    /**
     * @param \ReflectionClass $class
     * @param $annotationName
     * @return null
     */
    public function getClassAnnotation(\ReflectionClass $class, $annotationName)
    {
        $annotations = $this->getClassAnnotations($class);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationName) {
                return $annotation;
            }
        }

        return null;
    }

    /**
     * @param \ReflectionProperty $property
     * @return mixed
     */
    public function getPropertyAnnotations(\ReflectionProperty $property)
    {
        $cacheKey = $property->getDeclaringClass().'_'.$property->getName().'_property_annotations';
        if (!$this->cacheHas($cacheKey)) {
            $this->cacheAdd($cacheKey, $this->reader->getPropertyAnnotations($property));
        }
        return $this->cacheGet($cacheKey);
    }

    /**
     * @param \ReflectionProperty $property
     * @param $annotationName
     * @return null
     */
    public function getPropertyAnnotation(\ReflectionProperty $property, $annotationName)
    {
        $annotations = $this->getPropertyAnnotations($property);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationName) {
                return $annotation;
            }
        }

        return null;
    }

    /**
     * @param \ReflectionMethod $method
     * @return mixed
     */
    public function getMethodAnnotations(\ReflectionMethod $method)
    {
        $cacheKey = $method->getDeclaringClass().'_'.$method->getName().'_method_annotations';
        if (!$this->cacheHas($cacheKey)) {
            $this->cacheAdd($cacheKey, $this->reader->getMethodAnnotations($method));
        }
        return $this->cacheGet($cacheKey);
    }

    /**
     * @param \ReflectionMethod $method
     * @param $annotationName
     * @return null
     */
    public function getMethodAnnotation(\ReflectionMethod $method, $annotationName)
    {
        $annotations = $this->getMethodAnnotations($method);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationName) {
                return $annotation;
            }
        }

        return null;
    }

    // cache helper
    protected function cacheAdd($key, $data) {
        static::$inMemoryCache[$key] = $data;
    }
    protected function cacheHas($key) {
        return isset(static::$inMemoryCache[$key]);
    }
    protected function cacheGet($key) {
        return static::$inMemoryCache[$key];
    }
}
