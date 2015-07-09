<?php
namespace FS\SolrBundle\Doctrine\Mapper;

use FS\SolrBundle\Doctrine\Hydration\HydrationModes;
use FS\SolrBundle\Doctrine\Hydration\Hydrator;
use FS\SolrBundle\Doctrine\Mapper\Mapping\AbstractDocumentCommand;
use FS\SolrBundle\Doctrine\Annotation\Index as Solr;
use Solarium\QueryType\Update\Query\Document\Document;

class EntityMapper
{
    /**
     * @var CreateDocumentCommandInterface
     */
    private $mappingCommand = null;

    /**
     * @var Hydrator
     */
    private $doctrineHydrator;

    /**
     * @var Hydrator
     */
    private $indexHydrator;

    /**
     * @var string
     */
    private $hydrationMode = '';

    /**
     * @var MetaInformationFactory
     */
    private $metaInformationFactory;

    /**
     * @param Hydrator $doctrineHydrator
     * @param Hydrator $indexHydrator
     * @param MetaInformationFactory $metaInformationFactory
     */
    public function __construct(Hydrator $doctrineHydrator, Hydrator $indexHydrator, MetaInformationFactory $metaInformationFactory)
    {
        $this->doctrineHydrator = $doctrineHydrator;
        $this->indexHydrator = $indexHydrator;
        $this->hydrationMode = HydrationModes::HYDRATE_DOCTRINE;
        $this->metaInformationFactory = $metaInformationFactory;
    }

    /**
     * @param AbstractDocumentCommand $command
     */
    public function setMappingCommand(AbstractDocumentCommand $command)
    {
        $this->mappingCommand = $command;
    }

    /**
     * @param MetaInformation $meta
     * @return Document
     */
    public function toDocument(MetaInformation $meta)
    {
        if ($this->mappingCommand instanceof AbstractDocumentCommand) {
            return $this->mappingCommand->createDocument($meta);
        }

        return null;
    }

    /**
     * @param \ArrayAccess $document
     * @param object $sourceTargetEntity
     * @return object
     *
     * @throws \InvalidArgumentException if $sourceTargetEntity is null
     */
    public function toEntity(\ArrayAccess $document, $sourceTargetEntity)
    {
        if (null === $sourceTargetEntity) {
            throw new \InvalidArgumentException('$sourceTargetEntity should not be null');
        }

        // simple cache for meta information
        static $metaInformationCache = array();
        if (!array_key_exists($sourceTargetEntity, $metaInformationCache)) {
            $metaInformationCache[$sourceTargetEntity] = $this->metaInformationFactory->loadInformation($sourceTargetEntity);
        }
        $metaInformation = $metaInformationCache[$sourceTargetEntity];

        if ($metaInformation->isAbstract()) {
            foreach ($metaInformation->getDistriminatorMap() as $type => $class) {
                // todo: use \FS\SolrBundle\Doctrine\Mapper\MetaInformationFactory::getDocumentName
                if (strrpos($class, '\\')) {
                    $generatedDocumentName = strtolower(substr($class, (strrpos($class, '\\') + 1)));
                    $fullClass= $class;
                } else {
                    $generatedDocumentName = strtolower($class);
                    $fullClass = substr(
                        $metaInformation->getClassName(),
                        0,
                        strrpos($metaInformation->getClassName(), '\\') + 1
                    ) . $class;
                }

                if ($generatedDocumentName == $document->document_name_s) {
                    // todo: avoid instantiating objects
                    $metaInformation->setEntity(new $fullClass);
                }
            }
        }

        $hydratedDocument = $this->indexHydrator->hydrate($document, $metaInformation);
        if ($this->hydrationMode == HydrationModes::HYDRATE_INDEX) {
            return $hydratedDocument;
        }

        $metaInformation->setEntity($hydratedDocument);

        if ($this->hydrationMode == HydrationModes::HYDRATE_DOCTRINE) {
            return $this->doctrineHydrator->hydrate($document, $metaInformation);
        }
    }

    /**
     * @param string $mode
     */
    public function setHydrationMode($mode)
    {
        $this->hydrationMode = $mode;
    }
}
