<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 *
 * @category  Smile
 * @package   Smile\ElasticsuiteInventoryCatalog
 * @author    Richard BAYET <richard.bayet@smile.fr>
 * @copyright 2018 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */

namespace Smile\ElasticsuiteInventoryCatalog\Model\ResourceModel\Product\Indexer\Fulltext\Datasource;

use Smile\ElasticsuiteCatalog\Model\ResourceModel\Eav\Indexer\Indexer;
use Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Indexer\Fulltext\Datasource\InventoryDataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Smile\ElasticsuiteThesaurus\Model\Index;

/**
 * Multi Source Inventory Catalog Inventory Data source resource model
 *
 * @category Smile
 * @package  Smile\ElasticsuiteCatalog
 * @author   Richard Bayet <richard.bayet@smile.fr>
 */
class InventoryData extends Indexer implements InventoryDataInterface
{
    /**
     * @var StockResolverInterface
     */
    private $stockResolver;

    /**
     * @var StockIndexTableNameResolverInterface
     */
    private $stockIndexTableProvider;

    /**
     * @var int[]
     */
    private $stockIdByWebsite = [];

    /**
     * InventoryData constructor.
     *
     * @param ResourceConnection                   $resource                Database adapter.
     * @param StoreManagerInterface                $storeManager            Store manager.
     * @param MetadataPool                         $metadataPool            Metadata Pool
     * @param StockResolverInterface               $stockResolver           Stock resolver.
     * @param StockIndexTableNameResolverInterface $stockIndexTableProvider Stock index table provider.
     */
    public function __construct(
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        MetadataPool $metadataPool,
        StockResolverInterface $stockResolver,
        StockIndexTableNameResolverInterface $stockIndexTableProvider
    ) {
        $this->stockResolver = $stockResolver;
        $this->stockIndexTableProvider = $stockIndexTableProvider;

        parent::__construct($resource, $storeManager, $metadataPool);
    }

    /**
     * Load inventory data for a list of product ids and a given store.
     *
     * @param integer $storeId    Store id.
     * @param array   $productIds Product ids list.
     *
     * @return array
     */
    public function loadInventoryData($storeId, $productIds)
    {
        $websiteId = $this->getWebsiteId($storeId);
        $stockId   = $this->getStockId($websiteId);
        $tableName = $this->stockIndexTableProvider->execute($stockId);

        $select = $this->getConnection()->select()
            ->from(['product' => $this->getTable('catalog_product_entity')], [])
            ->join(
                ['stock_index' => $tableName],
                'product.sku = stock_index.' . IndexStructure::SKU,
                [
                    'product_id'    => 'product.entity_id',
                    'stock_status'  => IndexStructure::IS_SALABLE,
                    'qty'           => IndexStructure::QUANTITY,
                ]
            );

        return $this->getConnection()->fetchAll($select);
    }

    /**
     * Retrieve stock_id by website
     *
     * @param int $websiteId The website Id
     *
     * @return int
     */
    private function getStockId($websiteId)
    {
        if (!isset($this->stockIdByWebsite[$websiteId])) {
            $websiteCode = $this->storeManager->getWebsite($websiteId)->getCode();
            $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode);
            $stockId = (int) $stock->getStockId();
            $this->stockIdByWebsite[$websiteId] = $stockId;
        }

        return $this->stockIdByWebsite[$websiteId];
    }

    /**
     * Retrieve Website Id by Store Id
     *
     * @param int $storeId The store id
     *
     * @return int
     */
    private function getWebsiteId($storeId)
    {
        return $this->storeManager->getStore($storeId)->getWebsiteId();
    }
}
