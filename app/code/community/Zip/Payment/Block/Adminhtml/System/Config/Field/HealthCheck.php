<?php

class Zip_Payment_Block_Adminhtml_System_Config_Field_HealthCheck extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * @var string
     */
    protected $template = 'zip/payment/system/config/field/health_check.phtml';
    const HEALTH_CHECK_CACHE_ID = 'zip_payment_health_check';

    /**
     * Set template to itself
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate($this->template);
        }
        return $this;
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $result = $element->getValue();
        Mage::app()->saveCache($result['overall_status'], self::HEALTH_CHECK_CACHE_ID);
        $this->addData($result);

        return $this->_toHtml();
    }

    public function getStatusLabel($statusLevel = null)
    {
        $statusList = array(
            Zip_Payment_Model_Adminhtml_System_Config_Backend_HealthCheck::STATUS_SUCCESS => Mage::helper('zip_payment')->__('success'),
            Zip_Payment_Model_Adminhtml_System_Config_Backend_HealthCheck::STATUS_WARNING => Mage::helper('zip_payment')->__('warning'),
            Zip_Payment_Model_Adminhtml_System_Config_Backend_HealthCheck::STATUS_ERROR => Mage::helper('zip_payment')->__('error')
        );

        return (!is_null($statusLevel) && isset($statusList[$statusLevel])) ? $statusList[$statusLevel] : null;
    }


    
}