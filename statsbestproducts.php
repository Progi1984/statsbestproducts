<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class statsbestproducts extends ModuleGrid
{
    private $html = null;
    private $query = null;
    private $columns = null;
    private $default_sort_column = null;
    private $default_sort_direction = null;
    private $empty_message = null;
    private $paging_message = null;

    public function __construct()
    {
        $this->name = 'statsbestproducts';
        $this->tab = 'administration';
        $this->version = '2.0.1';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        parent::__construct();

        $this->default_sort_column = 'totalPriceSold';
        $this->default_sort_direction = 'DESC';
        $this->empty_message = $this->trans('An empty record-set was returned.', array(), 'Modules.Statsbestproducts.Admin');
        $this->paging_message = $this->trans('Displaying %1$s of %2$s', array('{0} - {1}', '{2}'), 'Admin.Global');

        $this->columns = array(
            array(
                'id' => 'reference',
                'header' => $this->trans('Reference', array(), 'Admin.Global'),
                'dataIndex' => 'reference',
                'align' => 'left'
            ),
            array(
                'id' => 'name',
                'header' => $this->trans('Name', array(), 'Admin.Global'),
                'dataIndex' => 'name',
                'align' => 'left'
            ),
            array(
                'id' => 'totalQuantitySold',
                'header' => $this->trans('Quantity sold', array(), 'Admin.Global'),
                'dataIndex' => 'totalQuantitySold',
                'align' => 'center'
            ),
            array(
                'id' => 'avgPriceSold',
                'header' => $this->trans('Price sold', array(), 'Modules.Statsbestproducts.Admin'),
                'dataIndex' => 'avgPriceSold',
                'align' => 'right'
            ),
            array(
                'id' => 'totalPriceSold',
                'header' => $this->trans('Sales', array(), 'Admin.Global'),
                'dataIndex' => 'totalPriceSold',
                'align' => 'right'
            ),
            array(
                'id' => 'averageQuantitySold',
                'header' => $this->trans('Quantity sold in a day', array(), 'Modules.Statsbestproducts.Admin'),
                'dataIndex' => 'averageQuantitySold',
                'align' => 'center'
            ),
            array(
                'id' => 'totalPageViewed',
                'header' => $this->trans('Page views', array(), 'Modules.Statsbestproducts.Admin'),
                'dataIndex' => 'totalPageViewed',
                'align' => 'center'
            ),
            array(
                'id' => 'quantity',
                'header' => $this->trans('Available quantity for sale', array(), 'Admin.Global'),
                'dataIndex' => 'quantity',
                'align' => 'center'
            ),
            array(
                'id' => 'active',
                'header' => $this->trans('Active', array(), 'Admin.Global'),
                'dataIndex' => 'active',
                'align' => 'center'
            )
        );

        $this->displayName = $this->trans('Best-selling products', array(), 'Modules.Statsbestproducts.Admin');
        $this->description = $this->trans('Enrich your stats with a small list of your best-sellers to better know your customers.', array(), 'Modules.Statsbestproducts.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return (parent::install() && $this->registerHook('displayAdminStatsModules'));
    }

    public function hookDisplayAdminStatsModules($params)
    {
        $engine_params = array(
            'id' => 'id_product',
            'title' => $this->displayName,
            'columns' => $this->columns,
            'defaultSortColumn' => $this->default_sort_column,
            'defaultSortDirection' => $this->default_sort_direction,
            'emptyMessage' => $this->empty_message,
            'pagingMessage' => $this->paging_message
        );

        if (Tools::getValue('export')) {
            $this->csvExport($engine_params);
        }

        return '<div class="panel-heading">'.$this->displayName.'</div>
		'.$this->engine($engine_params).'
		<a class="btn btn-default export-csv" href="'.Tools::safeOutput($_SERVER['REQUEST_URI'].'&export=1').'">
			<i class="icon-cloud-upload"></i> '.$this->trans('CSV Export', array(), 'Admin.Global').'
		</a>';
    }

    public function getData()
    {
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $date_between = $this->getDate();
        $array_date_between = explode(' AND ', $date_between);

        $this->query = 'SELECT SQL_CALC_FOUND_ROWS p.reference, p.id_product, pl.name,
				ROUND(AVG(od.unit_price_tax_excl / o.conversion_rate), 2) as avgPriceSold,
				IFNULL(stock.quantity, 0) as quantity,
				IFNULL(SUM(od.product_quantity), 0) AS totalQuantitySold,
				ROUND(IFNULL(IFNULL(SUM(od.product_quantity), 0) / (1 + LEAST(TO_DAYS('.$array_date_between[1].'), TO_DAYS(NOW())) - GREATEST(TO_DAYS('.$array_date_between[0].'), TO_DAYS(product_shop.date_add))), 0), 2) as averageQuantitySold,
				ROUND(IFNULL(SUM((od.unit_price_tax_excl * od.product_quantity) / o.conversion_rate), 0), 2) AS totalPriceSold,
				(
					SELECT IFNULL(SUM(pv.counter), 0)
					FROM '._DB_PREFIX_.'page pa
					LEFT JOIN '._DB_PREFIX_.'page_viewed pv ON pa.id_page = pv.id_page
					LEFT JOIN '._DB_PREFIX_.'date_range dr ON pv.id_date_range = dr.id_date_range
					WHERE pa.id_object = p.id_product AND pa.id_page_type = '.(int)Page::getPageTypeByName('product').'
					AND dr.time_start BETWEEN '.$date_between.'
					AND dr.time_end BETWEEN '.$date_between.'
				) AS totalPageViewed,
				product_shop.active
				FROM '._DB_PREFIX_.'product p
				'.Shop::addSqlAssociation('product', 'p').'
				LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (p.id_product = pl.id_product AND pl.id_lang = '.(int)$this->getLang().' '.Shop::addSqlRestrictionOnLang('pl').')
				LEFT JOIN '._DB_PREFIX_.'order_detail od ON od.product_id = p.id_product
				LEFT JOIN '._DB_PREFIX_.'orders o ON od.id_order = o.id_order
				'.Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o').'
				'.Product::sqlStock('p', 0).'
				WHERE o.valid = 1
				AND o.invoice_date BETWEEN '.$date_between.'
				GROUP BY od.product_id';

        if (Validate::IsName($this->_sort)) {
            $this->query .= ' ORDER BY `'.bqSQL($this->_sort).'`';
            if (isset($this->_direction) && Validate::isSortDirection($this->_direction)) {
                $this->query .= ' '.$this->_direction;
            }
        }

        if (($this->_start === 0 || Validate::IsUnsignedInt($this->_start)) && Validate::IsUnsignedInt($this->_limit)) {
            $this->query .= ' LIMIT '.(int)$this->_start.', '.(int)$this->_limit;
        }

        $values = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query);
        foreach ($values as &$value) {
            $value['avgPriceSold'] = $this->context->getCurrentLocale()->formatPrice($value['avgPriceSold'], $currency->iso_code);
            $value['totalPriceSold'] = $this->context->getCurrentLocale()->formatPrice($value['totalPriceSold'], $currency->iso_code);
        }
        unset($value);

        $this->_values = $values;
        $this->_totalCount = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT FOUND_ROWS()');
    }
}
