<?php

/* 
 *  @since      1.0.0
 *  @author     Peakhour.io Pty Ltd <support@peakhour.io>
 *  @copyright  Copyright (c) 2021 Peakhour.io Pty Ltd, (https://www.peakhour.io)
 *  @license    https://opensource.org/licenses/GPL-3.0
 *
 * The following code is a derivative work of the code from the LiteSpeed LSCache project,
 * which is licensed GPLv3. This code therefore is also licensed under the terms
 * of the GNU Public License, verison 3.
 */

class ControllerExtensionModulePeakhour extends Controller
{
    const LOG_ERROR = 3;
    const LOG_INFO = 6;
    const LOG_DEBUG = 8;
    private $error = array();

    public function index()
    {

        $data = $this->load->language('extension/module/peakhour');

        $currentLink = $this->url->link('extension/module/peakhour', 'user_token=' . $this->session->data['user_token'], true);
        $this->session->data['previouseURL'] = $currentLink;
        $parentLink = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $siteUrl = new Url(HTTP_CATALOG, HTTPS_CATALOG);
        $recacheLink = $siteUrl->link('extension/module/peakhour/recache', 'user_token=' . $this->session->data['user_token'], true);

        $action = 'index';
        if (isset($this->request->get['action'])) {
            $action = $this->request->get['action'];
        }
        $data['action'] = $action;

        if ($action == 'purgeAllButton') {
            $lscInstance = $this->peakhourInit(true);
        } else {
            $lscInstance = $this->peakhourInit();
        }

        if (isset($this->request->get['tab'])) {
            $data["tab"] = $this->request->get['tab'];
        } else {
            $data["tab"] = "general";
        }

        $this->load->model('extension/module/peakhour');
        $oldSetting = $this->model_extension_module_peakhour->getItems();

        if (!$this->validate()) {
            $this->log('Invalid Access', self::LOG_ERROR);
        } else if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['purgeURL'])) {
            $msg = $this->purgeUrls();
            $data["tab"] = "urls";
            $data['success'] = $msg;
        } else if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $this->session->data['peakhourOption'] = "debug";
            $this->model_extension_module_peakhour->editSetting('module_peakhour', $this->request->post);
            if (isset($this->session->data['error'])) {
                $data['error_warning'] = $this->session->data['error'];
                $this->session->data['error'] = "";
            } else {
                $this->session->data['success'] = $this->language->get('text_success');
                $data['success'] = $this->language->get('text_success');
            }

            if (isset($this->session->data["previouseTab"])) {
                $data["tab"] = $this->session->data["previouseTab"];
                unset($this->session->data["previouseTab"]);
            }

//            if (($oldSetting["module_peakhour_status"] != $this->request->post["module_peakhour_status"]) || ($oldSetting["module_peakhour_esi"] != $this->request->post["module_peakhour_esi"])) {
//                if ($lscInstance) {
//                    $lscInstance->purgeAllPublic();
//                    $data['success'] .= '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
//                }
//            }

            if (!isset($oldSetting["module_peakhour_vary_mobile"])) {
                $oldSetting["module_peakhour_vary_mobile"] = '0';
            }

            if (!isset($oldSetting["module_peakhour_vary_safari"])) {
                $oldSetting["module_peakhour_vary_safari"] = '0';
            }

//            if (($oldSetting["module_peakhour_vary_mobile"] != $this->request->post["module_peakhour_vary_mobile"]) || ($oldSetting["module_peakhour_vary_safari"] != $this->request->post["module_peakhour_vary_safari"])) {
//                $data['success'] = $this->language->get('text_commentHtaccess');
//                if ($lscInstance) {
//                    $lscInstance->purgeAllPublic();
//                    $data['success'] .= '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
//                }
//            }
        } else if (($action == 'purgeAll') && $lscInstance) {
            $result = $lscInstance->purgeAllPublic();
            $this->log($lscInstance->getLogBuffer());
            if ($result['success']) {
                $data['success'] = $this->language->get('text_purgeSuccess');
            } else {
                $data['error_warning'] = $result['error'];
            }
        } else if (($action == 'purgeAllButton') && $lscInstance) {
            $lscInstance->purgeAllPublic();
            $this->log($lscInstance->getLogBuffer());
            if (isset($_SERVER['HTTP_REFERER'])) {
                $this->response->redirect($_SERVER['HTTP_REFERER']);
                return;
            }
        } else if (($action == 'deletePage') && isset($this->request->get['key'])) {
            $key = $this->request->get['key'];
            $this->model_extension_module_peakhour->deleteSettingItem('module_peakhour', $key);
            if ($lscInstance) {
                $lscInstance->purgePublic($key);
                $this->log($lscInstance->getLogBuffer());
                $data['success'] = $this->language->get('text_purgeSuccess');
            }
            $data["tab"] = "pages";
        } else if ($action == 'addESIModule') {
            $data['moduleOptions'] = $this->model_extension_module_peakhour->getESIModuleOptions();
            $data['extensionOptions'] = $this->model_extension_module_peakhour->getESIExtensionOptions();
            $data["tab"] = "modules";
            $this->session->data['previouseTab'] = "modules";
        } else if (($action == 'deleteESI') && isset($this->request->get['key'])) {
            $key = $this->request->get['key'];
            $this->model_extension_module_peakhour->deleteSettingItem('module_peakhour', $key);
            if ($lscInstance) {
                $lscInstance->purgePublic($key);
                $this->log($lscInstance->getLogBuffer());
                $data['success'] = $this->language->get('text_purgeModule');
            }
            $data["tab"] = "modules";
        } else if (($action == 'purgeESI') && isset($this->request->get['key']) && $lscInstance) {
            $key = $this->request->get['key'];
            $lscInstance->purgePublic($key);
            $this->log($lscInstance->getLogBuffer());
            $data['success'] = $this->language->get('text_purgeModule');
            $data["tab"] = "modules";
        } else if (($action == 'purgePage') && isset($this->request->get['key']) && $lscInstance) {
            $key = $this->request->get['key'];
            $lscInstance->purgePublic($key);
            $this->log($lscInstance->getLogBuffer());
            $data['success'] = $this->language->get('text_purgeSuccess');
            $data["tab"] = "pages";
        } else if ($action == 'deleteESI') {
            $data["tab"] = "modules";
        } else if ($action == 'deletePage') {
            $data["tab"] = "pages";
        } else if ($action == 'addPage') {
            $this->session->data["previouseTab"] = "pages";
        } else if ($action == 'addESIRoute') {
            $this->session->data["previouseTab"] = "modules";
        }

        $data['pages'] = $this->model_extension_module_peakhour->getPages();
        $data['modules'] = $this->model_extension_module_peakhour->getModules();
        $items = $this->model_extension_module_peakhour->getItems();
        $data = array_merge($data, $items);

        $data['tabtool'] = new Tool('active', 'general');
        $data['selectEnable'] = new Tool('selected', '1');
        $data['selectDisable'] = new Tool('selected', '0');
        $data['selectDefault'] = new Tool('selected', '');
        $data['checkEnable'] = new Tool('checked', '1');
        $data['checkDisable'] = new Tool('checked', '0');

        if (!empty($data['error_warning'])) {
        } else if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/peakhour', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['cancel'] = $parentLink;
        $data['self'] = $currentLink;
        $data['purgeAll'] = $currentLink . '&action=purgeAll';
        $data['purgePage'] = $currentLink . '&action=purgePage';
        $data['purgeESI'] = $currentLink . '&action=purgeESI';
        $data['recacheAll'] = $this->isCurl() ? $recacheLink : '#';
        $data['addPage'] = $currentLink . '&tab=pages&action=addPage';
        $data['deletePage'] = $currentLink . '&tab=pages&action=deletePage';
        $data['addESIModule'] = $currentLink . '&tab=modules&action=addESIModule';
        $data['addESIRoute'] = $currentLink . '&tab=modules&action=addESIRoute';
        $data['deleteESI'] = $currentLink . '&tab=modules&action=deleteESI';

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('view/javascript//bootstrap-toggle.min.js');
        $this->document->addStyle('view/stylesheet//bootstrap-toggle.min.css');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/peakhour', $data));

    }


    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/peakhour')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }


    public function purgeAllButton($route, &$args, &$output)
    {
        if ($this->user && $this->user->hasPermission('modify', 'extension/module/peakhour')) {
            $lan = new Language();
            $lan->load('extension/module/peakhour');
            $button = '<li><a href="' . $this->url->link('extension/module/peakhour', 'user_token=' . $this->session->data['user_token'], true) . '&action=purgeAllButton' . '" data-toggle="tooltip" title="" class="btn" data-original-title="' . $lan->get('button_purgeAll') . '"><i class="fa fa-trash"></i><span class="hidden-xs hidden-sm hidden-md"> Purge All Peakhour Cache</span></a></li>';
            $search = '<ul class="nav navbar-nav navbar-right">';
            $output = str_replace($search, $search . $button, $output);
        }
    }

    public function install()
    {
        $this->load->model('setting/event');
        $this->load->model('extension/module/peakhour');
        $this->model_setting_event->addEvent('peakhour_init', 'catalog/controller/*/before', 'extension/module/peakhour/onAfterInitialize');

        $this->model_setting_event->addEvent('peakhour_button_purgeall', 'admin/controller/common/header/after', 'extension/module/peakhour/purgeAllButton');
        $this->model_setting_event->addEvent('peakhour_product_list', 'catalog/model/catalog/product/getProducts/after', 'extension/module/peakhour/getProducts');
        $this->model_setting_event->addEvent('peakhour_product_get', 'catalog/model/catalog/product/getProduct/after', 'extension/module/peakhour/getProduct');
        $this->model_setting_event->addEvent('peakhour_product_add', 'admin/model/catalog/product/addProduct/after', 'extension/module/peakhour/addProduct');
        $this->model_setting_event->addEvent('peakhour_product_edit', 'admin/model/catalog/product/editProduct/after', 'extension/module/peakhour/editProduct');
        $this->model_setting_event->addEvent('peakhour_product_delete', 'admin/model/catalog/product/deleteProduct/after', 'extension/module/peakhour/editProduct');

        $this->model_setting_event->addEvent('peakhour_category_list', 'catalog/model/catalog/category/getCategories/after', 'extension/module/peakhour/getCategories');
        $this->model_setting_event->addEvent('peakhour_category_get', 'catalog/model/catalog/category/getCategory/after', 'extension/module/peakhour/getCategory');
        $this->model_setting_event->addEvent('peakhour_category_add', 'admin/model/catalog/category/addCategory/after', 'extension/module/peakhour/addCategory');
        $this->model_setting_event->addEvent('peakhour_category_edit', 'admin/model/catalog/category/editCategory/after', 'extension/module/peakhour/editCategory');
        $this->model_setting_event->addEvent('peakhour_category_delete', 'admin/model/catalog/category/deleteCategory/after', 'extension/module/peakhour/editCategory');

        $this->model_setting_event->addEvent('peakhour_manufacturer_list', 'catalog/model/catalog/manufacturer/getManufacturers/after', 'extension/module/peakhour/getManufacturers');
        $this->model_setting_event->addEvent('peakhour_manufacturer_get', 'catalog/model/catalog/manufacturer/getManufacturer/after', 'extension/module/peakhour/getManufacturer');
        $this->model_setting_event->addEvent('peakhour_manufacturer_add', 'admin/model/catalog/manufacturer/addManufacturer/after', 'extension/module/peakhour/addManufacturer');
        $this->model_setting_event->addEvent('peakhour_manufacturer_edit', 'admin/model/catalog/manufacturer/editManufacturer/after', 'extension/module/peakhour/editManufacturer');
        $this->model_setting_event->addEvent('peakhour_manufacturer_delete', 'admin/model/catalog/manufacturer/deleteManufacturer/after', 'extension/module/peakhour/editManufacturer');

        $this->model_setting_event->addEvent('peakhour_information_list', 'catalog/model/catalog/information/getInformations/after', 'extension/module/peakhour/getInformations');
        $this->model_setting_event->addEvent('peakhour_information_get', 'catalog/model/catalog/information/getInformation/after', 'extension/module/peakhour/getInformation');
        $this->model_setting_event->addEvent('peakhour_information_add', 'admin/model/catalog/information/addInformation/after', 'extension/module/peakhour/addInformation');
        $this->model_setting_event->addEvent('peakhour_information_edit', 'admin/model/catalog/information/editInformation/after', 'extension/module/peakhour/editInformation');
        $this->model_setting_event->addEvent('peakhour_information_delete', 'admin/model/catalog/information/deleteInformation/after', 'extension/module/peakhour/editInformation');

//        $this->model_setting_event->addEvent('peakhour_checkout_confirm', 'catalog/controller/checkout/confirm/after', 'extension/module/peakhour/confirmOrder');
//        $this->model_setting_event->addEvent('peakhour_checkout_success', 'catalog/controller/checkout/success/after', 'extension/module/peakhour/confirmOrder');

        $this->model_setting_event->addEvent('peakhour_empty_cart', 'catalog/controller/common/cart/after', 'extension/module/peakhour/emptyCart');
        $this->model_setting_event->addEvent('peakhour_add_ajax', 'catalog/controller/common/header/after', 'extension/module/peakhour/addAjax');
//        $this->model_setting_event->addEvent('peakhour_cart_add', 'catalog/controller/checkout/cart/add/after', 'extension/module/peakhour/editCart');
//        $this->model_setting_event->addEvent('peakhour_cart_edit', 'catalog/controller/checkout/cart/edit/after', 'extension/module/peakhour/editCart');
//        $this->model_setting_event->addEvent('peakhour_cart_remove', 'catalog/controller/checkout/cart/remove/after', 'extension/module/peakhour/editCart');
        $this->model_setting_event->addEvent('peakhour_compare_check', 'catalog/controller/product/compare/add/before', 'extension/module/peakhour/checkCompare');
        $this->model_setting_event->addEvent('peakhour_compare_edit', 'catalog/controller/product/compare/add/after', 'extension/module/peakhour/editCompare');
        $this->model_setting_event->addEvent('peakhour_wishlist_check', 'catalog/controller/account/wishlist/add/before', 'extension/module/peakhour/checkWishlist');
        $this->model_setting_event->addEvent('peakhour_wishlist_edit', 'catalog/controller/account/wishlist/add/after', 'extension/module/peakhour/editWishlist');
        $this->model_setting_event->addEvent('peakhour_wishlist_display', 'catalog/controller/account/wishlist/after', 'extension/module/peakhour/editWishlist');

        $this->model_setting_event->addEvent('peakhour_user_forgotten', 'catalog/controller/account/forgotten/validate/after', 'extension/module/peakhour/onUserAfterLogin');
        $this->model_setting_event->addEvent('peakhour_user_login', 'catalog/model/account/customer/deleteLoginAttempts/after', 'extension/module/peakhour/onUserAfterLogin');
        $this->model_setting_event->addEvent('peakhour_user_logout', 'catalog/controller/account/logout/after', 'extension/module/peakhour/onUserAfterLogout');
        $this->model_setting_event->addEvent('peakhour_currency_change', 'catalog/controller/common/currency/currency/before', 'extension/module/peakhour/editCurrency');
        $this->model_setting_event->addEvent('peakhour_language_change', 'catalog/controller/common/language/language/before', 'extension/module/peakhour/editLanguage');

        $this->model_extension_module_peakhour->installPeakhour();
//        $this->initHtaccess();

        $lscInstance = $this->peakhourInit();

        if (function_exists('opcache_reset')) {
            opcache_reset();
        } else if (function_exists('phpopcache_reset')) {
            phpopcache_reset();
        }

        //clear template cache file
//        try {
//            $template = new Template($this->registry->get('config')->get('template_engine'));
//            $loader = new \Twig_Loader_Filesystem(DIR_TEMPLATE);
//            $twig = new \Twig_Environment($loader);
//            $name = 'extension/module/peakhour.twig';
//            $cls = $twig->getTemplateClass($name);
//            $cache = new Twig_Cache_Filesystem(DIR_CACHE);
//            $key = $cache->generateKey($name, $cls);
//            unlink($key);
//        } catch (Exception $ex) {
//        }

    }


    public function uninstall()
    {
        $this->load->model('setting/event');
        $this->load->model('extension/module/peakhour');
        $this->model_setting_event->deleteEventByCode('peakhour_debug');
        $this->model_setting_event->deleteEventByCode('peakhour_debug2');
        $this->model_setting_event->deleteEventByCode('peakhour_init');
        $this->model_setting_event->deleteEventByCode('peakhour_button_purgeall');
        $this->model_setting_event->deleteEventByCode('peakhour_product_list');
        $this->model_setting_event->deleteEventByCode('peakhour_product_add');
        $this->model_setting_event->deleteEventByCode('peakhour_product_get');
        $this->model_setting_event->deleteEventByCode('peakhour_product_edit');
        $this->model_setting_event->deleteEventByCode('peakhour_product_delete');
        $this->model_setting_event->deleteEventByCode('peakhour_category_list');
        $this->model_setting_event->deleteEventByCode('peakhour_category_add');
        $this->model_setting_event->deleteEventByCode('peakhour_category_get');
        $this->model_setting_event->deleteEventByCode('peakhour_category_edit');
        $this->model_setting_event->deleteEventByCode('peakhour_category_delete');
        $this->model_setting_event->deleteEventByCode('peakhour_manufacturer_list');
        $this->model_setting_event->deleteEventByCode('peakhour_manufacturer_add');
        $this->model_setting_event->deleteEventByCode('peakhour_manufacturer_get');
        $this->model_setting_event->deleteEventByCode('peakhour_manufacturer_edit');
        $this->model_setting_event->deleteEventByCode('peakhour_manufacturer_delete');
        $this->model_setting_event->deleteEventByCode('peakhour_information_list');
        $this->model_setting_event->deleteEventByCode('peakhour_information_add');
        $this->model_setting_event->deleteEventByCode('peakhour_information_get');
        $this->model_setting_event->deleteEventByCode('peakhour_information_edit');
        $this->model_setting_event->deleteEventByCode('peakhour_information_delete');

        $this->model_setting_event->deleteEventByCode('peakhour_checkout_confirm');
        $this->model_setting_event->deleteEventByCode('peakhour_checkout_success');
        $this->model_setting_event->deleteEventByCode('peakhour_cart_add');
        $this->model_setting_event->deleteEventByCode('peakhour_cart_edit');
        $this->model_setting_event->deleteEventByCode('peakhour_cart_remove');
        $this->model_setting_event->deleteEventByCode('peakhour_add_ajax');
        $this->model_setting_event->deleteEventByCode('peakhour_empty_cart');
        $this->model_setting_event->deleteEventByCode('peakhour_wishlist_display');
        $this->model_setting_event->deleteEventByCode('peakhour_wishlist_edit');
        $this->model_setting_event->deleteEventByCode('peakhour_wishlist_check');
        $this->model_setting_event->deleteEventByCode('peakhour_compare_check');
        $this->model_setting_event->deleteEventByCode('peakhour_compare_edit');
        $this->model_setting_event->deleteEventByCode('peakhour_user_forgotten');
        $this->model_setting_event->deleteEventByCode('peakhour_user_login');
        $this->model_setting_event->deleteEventByCode('peakhour_user_logout');
        $this->model_setting_event->deleteEventByCode('peakhour_currency_change');
        $this->model_setting_event->deleteEventByCode('peakhour_language_change');

//        $this->clearHtaccess();
        $lscInstance = $this->peakhourInit();
//        if($lscInstance){
//            $lscInstance->purgeAllPublic();
//            $this->log($lscInstance->getLogBuffer(), 0);
//        }
        $this->model_extension_module_peakhour->uninstallPeakhour();
    }

    protected function handlePurgeResult($lscInstance, $result)
    {
        $this->log($lscInstance->getLogBuffer());
        $lan = new Language();
        $lan->load('extension/module/peakhour');
        if ($result['success']) {
            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $lan->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        } else {
            $text_error = $this->language->get('text_success') . '<br><i class="fa fa-cross-circle"></i> ' . $lan->get('text_purgeError') . $result['error'];
            $this->language->set('text_success', $text_error);
        }
    }

    public function addProduct($route, &$args, &$output)
    {
        $lscInstance = $this->peakhourInit(true);
        if ($lscInstance) {
            $tags = [];
            $data = $args[0];
            if (isset($data['product_category'])) {
                foreach ($data['product_category'] as $category_id) {
                    $tags[] = 'C' . $category_id;
                }
            }
            $result = $lscInstance->purgeTags($tags);
            $this->handlePurgeResult($lscInstance, $result);
        }
    }


    public function editProduct($route, &$args, &$output)
    {
        $lscInstance = $this->peakhourInit(true);
        if ($lscInstance) {
            $tags[] = 'P' . $args[0];
            $this->load->model('catalog/product');
            $setting = $this->model_extension_module_peakhour->getItems();
            if (isset($setting['module_peakhour_purge_category']) && $setting['module_peakhour_purge_category']) {
                $categories = $this->model_catalog_product->getProductCategories($args[0]);
                foreach ($categories as $category) {
                    $tags[] = 'C' . $category;
                }
            }

            $result = $lscInstance->purgeTags($tags);
            $this->handlePurgeResult($lscInstance, $result);
        }
    }

    public function addCategory($route, &$args, &$output)
    {
        $lscInstance = $this->peakhourInit(true);
        if ($lscInstance) {
            $tags[] = 'Category';
            $result = $lscInstance->purgeTags($tags);
            $this->handlePurgeResult($lscInstance, $result);
        }
    }


    public function editCategory($route, &$args, &$output)
    {
        $lscInstance = $this->peakhourInit(true);
        if ($lscInstance) {
            $tags[] = 'C' . $args[0];
            $result = $lscInstance->purgeTags($tags);
            $this->handlePurgeResult($lscInstance, $result);
        }
    }


    public function addInformation($route, &$args, &$output)
    {
        $lscInstance = $this->peakhourInit(true);
        if ($lscInstance) {
            $tags[] = 'Information';
            $result = $lscInstance->purgeTags($tags);
            $this->handlePurgeResult($lscInstance, $result);
        }
    }


    public function editInformation($route, &$args, &$output)
    {
        $lscInstance = $this->peakhourInit(true);
        if ($lscInstance) {
            $tags[] = 'I' . $args[0];
            $result = $lscInstance->purgeTags($tags);
            $this->handlePurgeResult($lscInstance, $result);
        }
    }


    public function addManufacturer($route, &$args, &$output)
    {
        $lscInstance = $this->peakhourInit(true);
        if ($lscInstance) {
            $tags[] = 'Manufacturer';
            $result = $lscInstance->purgeTags($tags);
            $this->handlePurgeResult($lscInstance, $result);
        }
    }


    public function editManufacturer($route, &$args, &$output)
    {
        $lscInstance = $this->peakhourInit(true);
        if ($lscInstance) {
            $tags[] = 'M' . $args[0];
            $result = $lscInstance->purgeTags($tags);
            $this->handlePurgeResult($lscInstance, $result);
        }
    }


    private function peakhourInit($redirect = false)
    {

        $this->load->model('extension/module/peakhour');
        $setting = $this->model_extension_module_peakhour->getItems();

        if (isset($setting['module_peakhour_status']) && (!$setting['module_peakhour_status'])) {
            return false;
        }

        include_once(DIR_SYSTEM . 'library/peakhour/peakhourbase.php');
        include_once(DIR_SYSTEM . 'library/peakhour/peakhourcore.php');
        $lscInstance = new PeakhourCore($setting, $this->log);
        if (!$redirect) {
            $lscInstance->setHeaderFunction($this->response, 'addHeader');
        }
        return $lscInstance;
    }

    public function log($content = null, $logLevel = self::LOG_INFO)
    {
        if (empty($content)) {
            return;
        }

        $this->load->model('extension/module/peakhour');
        $setting = $this->model_extension_module_peakhour->getItems();

        if (!isset($setting['module_peakhour_log_level'])) {
            return;
        }

        $logLevelSetting = $setting['module_peakhour_log_level'];
        if (isset($this->session->data['peakhourOption']) && ($this->session->data['peakhourOption'] == "debug")) {
            $this->log->write($content);
            return;
        } else if ($logLevelSetting == self::LOG_DEBUG) {
            return;
        } else if ($logLevel > $logLevelSetting) {
            return;
        }

        $logInfo = "Peakhour Cache Info:";
        if ($logLevel == self::LOG_ERROR) {
            $logInfo = "Peakhour Module Error:";
        } else if ($logLevel == self::LOG_DEBUG) {
            $logInfo = "Peakhour Module Debug:";
        }

        $this->log->write($logInfo . $content);

    }

    protected function isCurl()
    {
        return function_exists('curl_version');
    }


    private function purgeUrls()
    {
        $lscInstance = $this->peakhourInit(true);
        $url = $this->request->post['peakhour_purge_url'];
        if (empty($url) || empty(trim($url))) {
            return;
        }

        $urls = explode("\n", str_replace(array("\r\n", "\r"), "\n", $url));
        $result = $lscInstance->purgeUrls($urls);

        if ($result['success']) {
            return 'URL(s) purged!';
        } else {
            return 'Error: ' . $result['error'];
        }
    }

}

final class Tool
{

    private $result;
    private $default;

    public function __construct($result = "", $default = "")
    {
        $this->result = $result;
        $this->default = $default;
    }

    public function check($value, $compare = "1", $attribute = "")
    {

        if ($value == "") {
            $value = $this->default;
        }

        if ($compare != $value) {
            return "";
        } else if (empty($attribute)) {
            return $this->result;
        } else {
            return $attribute . '="' . $this->result . '"';
        }

    }

}

