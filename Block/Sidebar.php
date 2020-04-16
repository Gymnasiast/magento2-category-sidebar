<?php namespace Sebwite\Sidebar\Block;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\View\Element\Template;

function endsWith($haystack, $needle) {
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}

/**
 * Class:Sidebar
 * Sebwite\Sidebar\Block
 *
 * @author      Sebwite
 * @package     Sebwite\Sidebar
 * @copyright   Copyright (c) 2015, Sebwite. All rights reserved
 */
class Sidebar extends Template
{

    /** * @var \Magento\Catalog\Helper\Category */
    protected $_categoryHelper;

    /** * @var \Magento\Framework\Registry */
    protected $_coreRegistry;

    /** * @var \Magento\Catalog\Model\Indexer\Category\Flat\State */
    protected $categoryFlatConfig;

    /** * @var \Magento\Catalog\Model\CategoryFactory */
    protected $_categoryFactory;

    /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection */
    protected $_productCollectionFactory;

    /** @var \Magento\Catalog\Helper\Output */
    private $helper;

	/** @var \Sebwite\Sidebar\Helper\Data */
    private $_dataHelper;

    /**
     * @param Template\Context                                        $context
     * @param \Magento\Catalog\Helper\Category                        $categoryHelper
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Catalog\Model\Indexer\Category\Flat\State      $categoryFlatState
     * @param \Magento\Catalog\Model\CategoryFactory                  $categoryFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollectionFactory
     * @param \Magento\Catalog\Helper\Output                          $helper
     * @param array                                                   $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryFlatState,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollectionFactory,
        \Magento\Catalog\Helper\Output $helper,
		\Sebwite\Sidebar\Helper\Data $dataHelper,
        $data = [ ]
    )
    {
        $this->_categoryHelper           = $categoryHelper;
        $this->_coreRegistry             = $registry;
        $this->categoryFlatConfig        = $categoryFlatState;
        $this->_categoryFactory          = $categoryFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->helper                    = $helper;
		$this->_dataHelper = $dataHelper;

        parent::__construct($context, $data);
    }

    /**
     * Get all categories
     *
     * @param bool $sorted
     * @param bool $asCollection
     * @param bool $toLoad
     *
     * @return array|\Magento\Catalog\Model\ResourceModel\Category\Collection|\Magento\Framework\Data\Tree\Node\Collection
     */
    public function getCategories($sorted = false, $asCollection = false, $toLoad = true)
    {
        $cacheKey = sprintf('%d-%d-%d-%d', $this->getSelectedRootCategoryId(), $sorted, $asCollection, $toLoad);
        if ( isset($this->_storeCategories[ $cacheKey ]) )
        {
            return $this->_storeCategories[ $cacheKey ];
        }

        /**
         * Check if parent node of the store still exists
         */
        $categoryCollection = $this->_categoryFactory->create();

		$categoryDepthLevel = $this->_dataHelper->getCategoryDepthLevel();

        $storeCategories = $categoryCollection->getCategories($this->getSelectedRootCategoryId(), $recursionLevel = $categoryDepthLevel, $sorted, $asCollection, $toLoad);

        $this->_storeCategories[ $cacheKey ] = $storeCategories;

        return $storeCategories;
    }

    /**
     * getSelectedRootCategoryId method
     *
     * @return int
     */
    public function getSelectedRootCategoryId()
    {
        $rootCategoryId = $this->_scopeConfig->getValue(
            'sebwite_sidebar/general/category'
        );

		if ( $rootCategoryId == 'current_category_children'){
			$currentCategory = $this->_coreRegistry->registry('current_category');
			if($currentCategory){
				return $currentCategory->getId();
			}
			return 1;
		}

		if ( $rootCategoryId == 'current_category_parent_children'){
			$currentCategory = $this->_coreRegistry->registry('current_category');
			if($currentCategory){
				$currentCategoryPath = $currentCategory->getPath();
				$currentCategoryPathArray = explode("/", $currentCategoryPath);
				if(isset($currentCategoryPath)){
					return array_reverse($currentCategoryPathArray)[2] ?: 1;
				}
			}
			return 1;
		}

        if ( $rootCategoryId === null )
        {
            return 1;
        }

        return $rootCategoryId;
    }

    /**
     * Retrieve subcategories
	 *
     * @param $category
     *
     * @return array
     */
    public function getSubcategories($category)
    {
        if ( $this->categoryFlatConfig->isFlatEnabled() && $category->getUseFlatResource() )
        {
            return (array)$category->getChildrenNodes();
        }

        return $category->getChildren();
    }

    public function isCurrentCategoryOrParentOfCurrentCategory($category)
    {
        $currentCategory = $this->_coreRegistry->registry('current_category');
        $currentProduct  = $this->_coreRegistry->registry('current_product');

        if ( !$currentCategory )
        {
            // Check if we're on a product page
            if ( $currentProduct !== null )
            {
                return in_array($category->getId(), $currentProduct->getCategoryIds());
            }

            return false;
        }

        $categoryPath = join(
            array_reverse(
                array_map(
                    function($c) {
                        return $c->getId();
                    },
                    $category->getPath()
                )
            ),
            "/"
        );

        // If the current category's path includes the whole path of that given category path,
        // it probably means the current category is either that directory, or a child of it.
        return strpos('/' . $currentCategory->getPath() . '/', '/' . $categoryPath . '/') !== false;
    }

    public function isCurrentCategory($category)
    {
        $currentCategory = $this->_coreRegistry->registry('current_category');
        if (!$currentCategory) {
            return false;
        }

        if ($currentCategory->getId() != $category->getId()) {
            return false;
        }

        $categoryPath = join(
            array_reverse(
                array_map(
                    function($c) {
                        return $c->getId();
                    },
                    $category->getPath()
                )
            ),
            "/"
        );

        // If the current category's path ends with that given category's path,
        // it probably means we're at the same path.
        return endsWith($currentCategory->getPath(), $categoryPath);
    }

    /**
     * @deprecated
     */
    public function isActive($category)
    {
        return $this->isCurrentCategoryOrParentOfCurrentCategory($category);
    }

    /**
     * Return Category Id for $category object
     *
     * @param $category
     *
     * @return string
     */
    public function getCategoryUrl($category)
    {
        return $this->_categoryHelper->getCategoryUrl($category);
    }

    /**
     * Return Is Enabled config option
     *
     * @return string
     */
    public function isEnabled()
    {
        return $this->_dataHelper->isEnabled();
    }

    /**
     * Return Title Text for menu
     *
     * @return string
     */
    public function getTitleText()
    {
        return $this->_dataHelper->getTitleText();
    }

    /**
     * Return Menu Open config option
     *
     * @return string
     */
    public function isOpenOnLoad()
    {
        return $this->_dataHelper->isOpenOnLoad();
    }
}