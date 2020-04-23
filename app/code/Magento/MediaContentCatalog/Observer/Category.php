<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MediaContentCatalog\Observer;

use Magento\Catalog\Model\Category as CatalogCategory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\MediaContentApi\Api\UpdateContentAssetLinksInterface;
use Magento\MediaContentApi\Api\Data\ContentIdentityInterfaceFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Observe the catalog_category_save_after event and run processing relation between category content and media asset.
 */
class Category implements ObserverInterface
{
    private const CONTENT_TYPE = 'catalog_category';
    private const TYPE = 'entityType';
    private const ENTITY_ID = 'entityId';
    private const FIELD = 'field';

    /**
     * @var UpdateContentAssetLinksInterface
     */
    private $updateContentAssetLinks;

    /**
     * @var array
     */
    private $fields;

    /**
     * @var ContentIdentityInterfaceFactory
     */
    private $contentIdentityFactory;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ContentIdentityInterfaceFactory $contentIdentityFactory
     * @param UpdateContentAssetLinksInterface $updateContentAssetLinks
     * @param ResourceConnection $resourceConnection
     * @param array $fields
     */
    public function __construct(
        ContentIdentityInterfaceFactory $contentIdentityFactory,
        UpdateContentAssetLinksInterface $updateContentAssetLinks,
        ResourceConnection $resourceConnection,
        array $fields
    ) {
        $this->contentIdentityFactory = $contentIdentityFactory;
        $this->resourceConnection = $resourceConnection;
        $this->updateContentAssetLinks = $updateContentAssetLinks;
        $this->fields = $fields;
    }

    /**
     * Retrieve the saved category and pass it to the model processor to save content - asset relations
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        $model = $observer->getEvent()->getData('category');

        if ($model instanceof CatalogCategory) {
            foreach ($this->fields as $field) {
                $this->updateContentAssetLinks->execute(
                    $this->contentIdentityFactory->create(
                        [
                            self::TYPE => self::CONTENT_TYPE,
                            self::FIELD => $field,
                            self::ENTITY_ID => (string) $model->getId(),
                        ]
                    ),
                    implode(PHP_EOL, $this->getContent(
                        $model->getAttributes()[$field],
                        (int)$model->getEntityId())
                    )
                );
            }
        }
    }

    /**
     * @param $attribute
     * @param int $entityId
     * @return array
     */
    private function getContent($attribute, int $entityId): array
    {
        $connection = $this->resourceConnection->getConnection();

        /** @var  $attribute \Magento\Eav\Model\Entity\Attribute\AbstractAttribute */
        $select = $connection->select()->from(
            $attribute->getBackendTable(),
            'value'
        )->where(
            'attribute_id = ?',
            (int) $attribute->getId()
        )->where(
            'entity_id = ?',
            $entityId
        )->distinct(true);
        return $connection->fetchCol($select);
    }
}
