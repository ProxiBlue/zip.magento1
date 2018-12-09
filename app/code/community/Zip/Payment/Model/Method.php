<?php


class Zip_Payment_Model_Method extends Mage_Payment_Model_Method_Abstract
{
    const ACTION_IMMEDIATE_CAPTURE = 'immediate_capture';

    protected $_code = Zip_Payment_Model_Config::METHOD_CODE;
    
    protected $_formBlockType = 'zip_payment/method_form';
    protected $_infoBlockType = 'zip_payment/method_info';

    /**
     * Config instance
     * @var Zip_Payment_Model_Config
     */
    protected $config = null;
    protected $api = null;
    protected $logger = null;
    
    /**
     * Payment Method features
     * @var bool
     */
    protected $_isGateway                   = false;
    protected $_canOrder                    = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canCaptureOnce              = false;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canReviewPayment            = true;
    protected $_canCreateBillingAgreement   = true;
    protected $_canManageRecurringProfiles  = false;

    /**
     * We have void capture method but merchant might accidentally void
     * transaction in admin so do not implement this yet
     *
     * @var boolean
     */
    protected $_canCancelInvoice            = false;


    /**
     * lazy load API class once when needed
     *
     * @return Zip_Payment_Model_Api
     */
    public function getApi()
    {
        if ($this->api === null) {
            $this->api = Mage::getSingleton('zip_payment/api', $this->getConfig()->getApiConfiguration());
        }

        return $this->api;
    }

    /**
     * Config instance getter
     * @return Zip_Payment_Model_Config
     */
    public function getConfig()
    {
        if ($this->config == null) {
            $storeId = Mage::app()->getStore()->getStoreId();
            $this->config = Mage::getModel('zip_payment/config', $storeId);
            $this->config->setMethod($this->getCode());
        }
        return $this->config;
    }

    public function getApiConfig() {
        return $this->getConfig()->getApiConfiguration();
    }

    /**
     * Get logger object
     * @return Zip_Payment_Model_Logger
     */
    public function getLogger()
    {
        if ($this->logger == null) {
            $this->logger = Mage::getSingleton('zip_payment/logger');
        }
        return $this->logger;
    }


    /**
     * Retrieve model helper
     *
     * @return Zip_Payment_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('zip_payment');
    }

     /**
     * Log debug data to file
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData)
    {
        if ($this->getDebugFlag()) {
            $this->getLogger()->log($debugData);

        }
    }

    /**
     * Define if debugging is enabled
     *
     * @return bool
     */
    public function getDebugFlag()
    {
        return $this->getConfig()->isDebugEnabled();
    }

    /**
     * Check whether payment method can be used
     * @param Mage_Sales_Model_Quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return parent::isAvailable($quote) && $this->getConfig()->isMethodAvailable();
    }

     /**
     * Check method for processing with base currency
     *
     * @param string $currencyCode
     * @return boolean
     */
    public function canUseForCurrency($currencyCode)
    {
        return $this->getConfig()->isCurrencySupported($currencyCode);
    }

    /**
     * Custom getter for payment configuration
     *
     * @param string $field
     * @param int $storeId
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        return $this->getConfig()->getValue("payment/{$this->getCode()}/{$field}");
    }

    /**
     * Get session namespace
     *
     * @return Zip_Payment_Model_Session
     */
    public function getSession()
    {
        return Mage::getSingleton('zip_payment/session');
    }

     /**
     * Return checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }


    /******************* Redirect Url *****************/

    /**
     * Returns the url to redirect after placing the order
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->getHelper()->getUrl('zip_payment/checkout/start');
    }

     /******************* Payment Actions *****************/


    public function order(Varien_Object $payment, $amount)
    {
        if (!$this->canOrder()) {
            Mage::throwException($this->_getHelper()->__('Order action is not available.'));
        }
        return $this;
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            Mage::throwException($this->_getHelper()->__('Authorize action is not available.'));
        }
        
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        if (!$this->canCapture()) {
            Mage::throwException($this->_getHelper()->__('Capture action is not available.'));
        }

        $authorizationTransaction = $payment->getAuthorizationTransaction();
        $authId = $authorizationTransaction->getTransactionId();
        $checkoutId = $this->getSession()->getZipCheckoutId();

        if ($authId) {

            try {

                $charge = Mage::getModel('zip_payment/api_charge')
                ->setApiConfig($this->getApiConfig())
                ->setOrder($payment->getOrder())
                ->setPaymentAction($this->getConfigPaymentAction())
                ->create()
                ->capture($amount)
                ->getResponse();

                $payment
                ->setTransactionId($charge->getId())
                ->setIsTransactionApproved(true)
                ->setParentTransactionID($authId)
                ->setIsTransactionClosed(0)
                ->setAdditionalInformation("receipt_number", $charge->getReceiptNumber());

            } catch (Exception $e) {
                Mage::throwException($this->_getHelper()->__('Could not capture the payment - ' . $e->getMessage()));
            }

        }
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if (!$this->canRefund()) {
            Mage::throwException($this->_getHelper()->__('Refund action is not available.'));
        }

        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        return $this;
    }

    public function void(Varien_Object $payment)
    {
        if (!$this->canVoid($payment)) {
            Mage::throwException($this->_getHelper()->__('Void action is not available.'));
        }

        return $this;
    }

     /**
     * Set capture transaction ID to invoice for informational purposes
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function processInvoice($invoice, $payment)
    {
        $invoice->setTransactionId($payment->getLastTransId());
        return $this;
    }    

}