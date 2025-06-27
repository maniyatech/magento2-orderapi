<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

namespace ManiyaTech\OrderApi\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ExportDays implements ArrayInterface
{
    /**
     * Return array of options (1 - 30)
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        for ($i = 1; $i <= 30; $i++) {
            $options[] = ['value' => $i, 'label' => __("$i Day(s)")];
        }

        return $options;
    }
}
