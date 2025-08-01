<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

namespace ManiyaTech\OrderApi\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Format implements ArrayInterface
{
    public const CSV  = 'csv';
    public const XLSX  = 'xlsx';

    /**
     * Return array of options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            self::CSV  => __('CSV'),
            self::XLSX  => __('XLSX'),
        ];
    }
}
