<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

namespace ManiyaTech\OrderApi\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class DeleteExportFile implements ArrayInterface
{
    public const EVERY_1_D  = '0 0 * * *';
    public const EVERY_1_W  = '0 0 * * 0';
    public const EVERY_1_M  = '0 0 1 * *';

    /**
     * Return array of options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            self::EVERY_1_D  => __('Everyday'),
            self::EVERY_1_W  => __('Weekly'),
            self::EVERY_1_M  => __('Monthly'),
        ];
    }
}
