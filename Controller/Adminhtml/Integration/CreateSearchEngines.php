<?php

declare(strict_types=1);

namespace Doofinder\Feed\Controller\Adminhtml\Integration;

use Doofinder\Feed\Helper\SearchEngine;
use Doofinder\Feed\Helper\StoreConfig;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class CreateSearchEngines extends Action implements HttpGetActionInterface
{
    private const INSTALLING_LOOP_STEP = 2;

    /** @var StoreConfig */
    private $storeConfig;

    /** @var SearchEngine */
    private $searchEngineHelper;

    /** @var WriterInterface */
    private $configWriter;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var Escaper */
    protected $escaper;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        StoreConfig $storeConfig,
        SearchEngine $searchEngineHelper,
        WriterInterface $configWriter,
        JsonFactory $resultJsonFactory,
        Escaper $escaper,
        LoggerInterface $logger,
        Context $context
    ) {
        $this->storeConfig = $storeConfig;
        $this->searchEngineHelper = $searchEngineHelper;
        $this->configWriter = $configWriter;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->escaper = $escaper;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     *
     * @throws WebapiException
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            foreach ($this->storeConfig->getAllStores() as $store) {
                $searchEngineData = $this->getSearchEngineFromStore($store);
                $searchEngine = $this->searchEngineHelper->createSearchEngine($searchEngineData);
                $this->configWriter->save(
                    StoreConfig::HASH_ID_CONFIG,
                    $searchEngine['hashid'],
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
            }
            $resultJson->setData(true);
        } catch (Exception $e) {
            $this->storeConfig->setInstallingLoopStatus(self::INSTALLING_LOOP_STEP);
            $this->logger->error('Initial Setup error: ' . $e->getMessage());
            $resultJson->setHttpResponseCode(WebapiException::HTTP_INTERNAL_ERROR);
            $resultJson->setData(__('Create Search Engines'));
        }

        return $resultJson;
    }

    /**
     * Get search engine info from store view
     *
     * @param StoreInterface $store
     * @return array
     */
    private function getSearchEngineFromStore(StoreInterface $store): array
    {
        return [
            "language"  => $this->storeConfig->getLanguageFromStore($store),
            "name"      => $store->getName(),
            "site_url"  => $store->getBaseUrl(),
            "stopwords" => false,
            "platform"  => "ecommerce"
        ];
    }
}
