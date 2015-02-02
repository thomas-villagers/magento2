<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Customer\Block\Adminhtml\Edit\Tab\View;

use Magento\Customer\Api\Data\CustomerDataBuilder;
use Magento\Customer\Controller\RegistryConstants;

/**
 * Magento\Customer\Block\Adminhtml\Edit\Tab\View
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea adminhtml
 */
class PersonalInfoTest extends \PHPUnit_Framework_TestCase
{
    /** @var  \Magento\Backend\Block\Template\Context */
    private $_context;

    /** @var  \Magento\Framework\Registry */
    private $_coreRegistry;

    /** @var  CustomerDataBuilder */
    private $_customerBuilder;

    /** @var  \Magento\Customer\Api\CustomerRepositoryInterface */
    private $_customerRepository;

    /** @var  \Magento\Customer\Api\GroupRepositoryInterface */
    private $_groupRepository;

    /** @var \Magento\Framework\Store\StoreManagerInterface */
    private $_storeManager;

    /** @var \Magento\Framework\ObjectManagerInterface */
    private $_objectManager;

    /** @var \Magento\Framework\Reflection\DataObjectProcessor */
    private $_dataObjectProcessor;

    /** @var  PersonalInfo */
    private $_block;

    /**
     * @var \Magento\Framework\Stdlib\DateTime
     */
    protected $dateTime;

    public function setUp()
    {
        $this->_objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->_storeManager = $this->_objectManager->get('Magento\Framework\Store\StoreManagerInterface');
        $this->_context = $this->_objectManager->get(
            'Magento\Backend\Block\Template\Context',
            ['storeManager' => $this->_storeManager]
        );

        $this->_customerBuilder = $this->_objectManager->get('Magento\Customer\Api\Data\CustomerDataBuilder');
        $this->_coreRegistry = $this->_objectManager->get('Magento\Framework\Registry');
        $this->_customerRepository = $this->_objectManager->get(
            'Magento\Customer\Api\CustomerRepositoryInterface'
        );
        $this->_dataObjectProcessor = $this->_objectManager->get('Magento\Framework\Reflection\DataObjectProcessor');

        $this->_groupRepository = $this->_objectManager->get('Magento\Customer\Api\GroupRepositoryInterface');
        $this->dateTime = $this->_objectManager->get('Magento\Framework\Stdlib\DateTime');

        $this->_block = $this->_objectManager->get(
            'Magento\Framework\View\LayoutInterface'
        )->createBlock(
            'Magento\Customer\Block\Adminhtml\Edit\Tab\View\PersonalInfo',
            '',
            [
                'context' => $this->_context,
                'groupService' => $this->_groupRepository,
                'registry' => $this->_coreRegistry
            ]
        );
    }

    public function tearDown()
    {
        $this->_coreRegistry->unregister(RegistryConstants::CURRENT_CUSTOMER_ID);
        /** @var \Magento\Customer\Model\CustomerRegistry $customerRegistry */
        $customerRegistry = $this->_objectManager->get('Magento\Customer\Model\CustomerRegistry');
        //Cleanup customer from registry
        $customerRegistry->remove(1);
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testGetCustomer()
    {
        $this->assertEquals($this->_loadCustomer(), $this->_block->getCustomer());
    }

    public function testGetCustomerEmpty()
    {
        $this->assertEquals($this->_createCustomer(), $this->_block->getCustomer());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testGetGroupName()
    {
        $groupName = $this->_groupRepository->getById($this->_loadCustomer()->getGroupId())->getCode();
        $this->assertEquals($groupName, $this->_block->getGroupName());
    }

    public function testGetGroupNameNull()
    {
        $this->_createCustomer();
        $this->assertNull($this->_block->getGroupName());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testGetCreateDate()
    {
        $createdAt = $this->_block->formatDate(
            $this->_loadCustomer()->getCreatedAt(),
            \Magento\Framework\Stdlib\DateTime\TimezoneInterface::FORMAT_TYPE_MEDIUM,
            true
        );
        $this->assertEquals($createdAt, $this->_block->getCreateDate());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testGetStoreCreateDate()
    {
        $customer = $this->_loadCustomer();
        $date = $this->_context->getLocaleDate()->scopeDate(
            $customer->getStoreId(),
            $this->dateTime->toTimestamp($customer->getCreatedAt()),
            true
        );
        $storeCreateDate = $this->_block->formatDate(
            $date,
            \Magento\Framework\Stdlib\DateTime\TimezoneInterface::FORMAT_TYPE_MEDIUM,
            true
        );
        $this->assertEquals($storeCreateDate, $this->_block->getStoreCreateDate());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testGetStoreCreateDateTimezone()
    {
        /**
         * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface $defaultTimeZonePath
         */
        $defaultTimeZonePath = $this->_objectManager->get('Magento\Framework\Stdlib\DateTime\TimezoneInterface')
            ->getDefaultTimezonePath();
        $timezone = $this->_context->getScopeConfig()->getValue(
            $defaultTimeZonePath,
            \Magento\Framework\Store\ScopeInterface::SCOPE_STORE,
            $this->_loadCustomer()->getStoreId()
        );
        $this->assertEquals($timezone, $this->_block->getStoreCreateDateTimezone());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testIsConfirmedStatusConfirmed()
    {
        $this->_loadCustomer();
        $this->assertEquals('Confirmed', $this->_block->getIsConfirmedStatus());
    }

    /**
     * @magentoDbIsolation enabled
     */
    public function testIsConfirmedStatusConfirmationIsNotRequired()
    {
        $password = 'password';
        /** @var \Magento\Customer\Api\Data\CustomerInterface $customer */
        $customer = $this->_customerBuilder->setConfirmation(
            true
        )->setFirstname(
            'firstname'
        )->setLastname(
            'lastname'
        )->setEmail(
            'email@email.com'
        )->create();
        $customer = $this->_customerRepository->save($customer, $password);
        $this->_coreRegistry->register(RegistryConstants::CURRENT_CUSTOMER_ID, $customer->getId());
        $this->assertEquals('Confirmation Not Required', $this->_block->getIsConfirmedStatus());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testGetCreatedInStore()
    {
        $storeName = $this->_storeManager->getStore($this->_loadCustomer()->getStoreId())->getName();
        $this->assertEquals($storeName, $this->_block->getCreatedInStore());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/Customer/_files/customer_address.php
     */
    public function testGetBillingAddressHtml()
    {
        $this->_loadCustomer();
        $html = $this->_block->getBillingAddressHtml();
        $this->assertContains('John Smith<br/>', $html);
        $this->assertContains('Green str, 67<br />', $html);
        $this->assertContains('CityM,  Alabama, 75477<br/>', $html);
    }

    public function testGetBillingAddressHtmlNoDefaultAddress()
    {
        $this->_createCustomer();
        $this->assertEquals(
            __('The customer does not have default billing address.'),
            $this->_block->getBillingAddressHtml()
        );
    }

    /**
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    private function _createCustomer()
    {
        /** @var \Magento\Customer\Api\Data\CustomerInterface $customer */
        $customer = $this->_customerBuilder->setFirstname(
            'firstname'
        )->setLastname(
            'lastname'
        )->setEmail(
            'email@email.com'
        )->create();
        $data = ['account' => $this->_dataObjectProcessor
            ->buildOutputDataArray($customer, 'Magento\Customer\Api\Data\CustomerInterface'), ];
        $this->_context->getBackendSession()->setCustomerData($data);
        return $customer;
    }

    /**
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    private function _loadCustomer()
    {
        $customer = $this->_customerRepository->getById(1);
        $data = ['account' => $this->_dataObjectProcessor
            ->buildOutputDataArray($customer, 'Magento\Customer\Api\Data\CustomerInterface'), ];
        $this->_context->getBackendSession()->setCustomerData($data);
        $this->_coreRegistry->register(RegistryConstants::CURRENT_CUSTOMER_ID, $customer->getId());
        return $customer;
    }
}
