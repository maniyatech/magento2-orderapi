<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

namespace ManiyaTech\OrderApi\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\View\Element\Html\Select;
use ManiyaTech\OrderApi\Block\Adminhtml\System\Config\Form\Field\OrderAttributeCode;

class OrderExportFields extends AbstractFieldArray
{
    /**
     * @var \Magento\Framework\Data\Form\Element\Renderer\RendererInterface
     */
    protected $renderer;

    /**
     * Prepare to render dynamic rows
     */
    protected function _prepareToRender()
    {
        $this->addColumn('order_title', [
            'label' => __('Label'),
            'class' => 'required-entry',
        ]);

        $this->addColumn('order_code', [
            'label' => __('Code'),
            'renderer' => $this->getCodeRenderer()
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Attribute');
    }

    /**
     * Get order attribute code renderer for dynamic rows in system config.
     */
    protected function getCodeRenderer()
    {
        if (!$this->renderer) {
            $this->renderer = $this->getLayout()->createBlock(
                OrderAttributeCode::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->renderer;
    }

    /**
     * Prepare each row with selected option.
     *
     * @param \Magento\Framework\DataObject $row
     * @return void
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row): void
    {
        $options = [];
        $code = $row->getData('order_code');
        if ($code !== null) {
            $options['option_' . $this->getCodeRenderer()->calcOptionHash($code)] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }
}
