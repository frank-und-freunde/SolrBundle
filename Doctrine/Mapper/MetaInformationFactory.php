<?php
namespace FS\SolrBundle\Doctrine\Mapper;

use FS\SolrBundle\Doctrine\Annotation\AnnotationReader;
use FS\SolrBundle\Doctrine\ClassnameResolver\ClassnameResolver;
use FS\SolrBundle\Doctrine\Configuration;

/**
 *
 * @author fs
 *
 */
class MetaInformationFactory
{

    /**
     * @var MetaInformation
     */
    private $metaInformations = null;

    /**
     * @var AnnotationReader
     */
    private $annotationReader = null;

    /**
     * @var ClassnameResolver
     */
    private $classnameResolver = null;

    public function __construct()
    {
        $this->annotationReader = new AnnotationReader();
    }

    /**
     * @param ClassnameResolver $classnameResolver
     */
    public function setClassnameResolver(ClassnameResolver $classnameResolver)
    {
        $this->classnameResolver = $classnameResolver;
    }

    /**
     * @return \FS\SolrBundle\Doctrine\ClassnameResolver\ClassnameResolver
     */
    public function getClassnameResolver()
    {
        return $this->classnameResolver;
    }

    /**
     * @param string|object entityAlias
     * @return MetaInformation
     */
    public function loadInformation($entity)
    {
        $className = $this->getClass($entity);

        if (!is_object($entity)) {
            $entity = null;
        }

        $reflection = new \ReflectionClass($className);

        /*
        if (!is_object($entity) && !$reflection->isAbstract()) {
            $entity = new $className;
        }
        */

        if (!$this->annotationReader->hasDocumentDeclaration($className)) {
            return null;
        }

        $metaInformation = new MetaInformation();
        $metaInformation->setEntity($entity);
        $metaInformation->setClassName($className);
        $metaInformation->setDocumentName($this->getDocumentName($className));
        $metaInformation->setFieldMapping(
            $this->annotationReader->getFieldMapping($entity ?: $className)
        );
        $metaInformation->setFields(
            $this->annotationReader->getFields($entity ?: $className)
        );
        $metaInformation->setRepository(
            $this->annotationReader->getRepository($entity ?: $className)
        );
        $metaInformation->setIdentifier
            ($this->annotationReader->getIdentifier($entity ?: $className)
            );
        $metaInformation->setBoost(
            $this->annotationReader->getEntityBoost($entity ?: $className)
        );

        if ($reflection->isAbstract()) {
            $metaInformation->setIsAbstract(true);
            $metaInformation->setDistriminatorMap($this->annotationReader->getDistriminatorMap($className));
        } else {
            $metaInformation->setSynchronizationCallback($this->annotationReader->getSynchronizationCallback($entity));
        }

        return $metaInformation;
    }

    /**
     * @param object $entity
     * @throws \RuntimeException
     * @return string
     */
    private function getClass($entity)
    {
        if (is_object($entity)) {
            return get_class($entity);
        }

        if (class_exists($entity)) {
            return $entity;
        }

        $realClassName = $this->classnameResolver->resolveFullQualifiedClassname($entity);

        return $realClassName;
    }

    /**
     * @param string $fullClassName
     * @return string
     */
    private function getDocumentName($fullClassName)
    {
        $className = substr($fullClassName, (strrpos($fullClassName, '\\') + 1));

        return strtolower($className);
    }
}
