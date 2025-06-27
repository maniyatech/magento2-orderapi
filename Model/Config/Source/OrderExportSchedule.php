<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

namespace ManiyaTech\OrderApi\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class OrderExportSchedule implements ArrayInterface
{
    public const EVERY_3_H  = '0 */3 * * *';
    public const EVERY_6_H  = '0 */6 * * *';
    public const EVERY_12_H = '0 */12 * * *';
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
            self::EVERY_3_H  => __('Every 3 Hours'),
            self::EVERY_6_H  => __('Every 6 Hours'),
            self::EVERY_12_H => __('Every 12 Hours'),
            self::EVERY_1_D  => __('Everyday'),
            self::EVERY_1_W  => __('Weekly'),
            self::EVERY_1_M  => __('Monthly'),
        ];
    }
}
