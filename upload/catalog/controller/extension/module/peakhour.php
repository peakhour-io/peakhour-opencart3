<?php

/*
 *  @since      1.0.0
 *  @author     Peakhour.io Pty Ltd <support@peakhour.io>
 *  @copyright  Copyright (c) 2021 Peakhour Technologies, (https://www.peakhour.io)
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

    public function onAfterInitialize($route, &$args)
    {

        //$this->log->write('init:' . $route . PHP_EOL);

        if ($this->peakhour == null) {
            //pass
        } else if ($route == "extension/module/peakhour/renderESI") {
            return; //ESI render
        } else if ($this->peakhour->pageCachable) {
            return;
        } else if ($this->peakhour->cacheEnabled) {
            $this->onAfterRoute($route, $args);
            return;
        } else {
            return;
        }

        $this->peakhour = (object) array('route' => $route, 'setting' => null, 'cacheEnabled' => false, 'pageCachable' => false, 'urlRule' => false, 'esiEnabled' => false, 'esiOn' => false, 'cacheTags' => array(), 'lscInstance' => null, 'pages' => null, 'includeUrls' => null);

        $this->load->model('extension/module/peakhour');
        $this->peakhour->setting = $this->model_extension_module_peakhour->getItems();
        $this->peakhour->pages = $this->model_extension_module_peakhour->getPages();

        if (isset($this->peakhour->setting['module_peakhour_status']) && (!$this->peakhour->setting['module_peakhour_status'])) {
            return;
        }

        $this->peakhour->cacheEnabled = true;
        $this->peakhour->esiEnabled = false;

        include_once(DIR_SYSTEM . 'library/peakhour/peakhourbase.php');
        include_once(DIR_SYSTEM . 'library/peakhour/peakhourcore.php');
        $this->peakhour->lscInstance = new PeakhourCore($this->peakhour->setting, $this->log);
        $this->peakhour->lscInstance->setHeaderFunction($this->response, 'addHeader');

        $includeUrls = isset($this->peakhour->setting['module_peakhour_include_urls']) ? explode(PHP_EOL, $this->peakhour->setting['module_peakhour_include_urls']) : null;
        $this->peakhour->includeUrls = $includeUrls;
        $excludeLoginUrls = isset($this->peakhour->setting['module_peakhour_exclude_login_urls']) ? explode(PHP_EOL, $this->peakhour->setting['module_peakhour_exclude_login_urls']) : null;
        $excludeUrls = isset($this->peakhour->setting['module_peakhour_exclude_urls']) ? explode(PHP_EOL, $this->peakhour->setting['module_peakhour_exclude_urls']) : null;
        $uri = trim($_SERVER['REQUEST_URI']);

        if ($includeUrls && in_array($uri, $includeUrls)) {
            $this->peakhour->pageCachable = true;
            $this->peakhour->urlRule = true;
        }

        if ($this->customer->isLogged() && $excludeLoginUrls && in_array($uri, $excludeLoginUrls)) {
            $this->peakhour->pageCachable = false;
            $this->peakhour->urlRule = true;
        }

        if ($excludeUrls && in_array($uri, $excludeUrls)) {
            $this->peakhour->pageCachable = false;
            $this->peakhour->urlRule = true;
        }

        if ($route != "extension/module/peakhour/renderESI") {
            $this->onAfterRoute($route, $args);
        }
    }

    public function onAfterRoute($route, &$args)
    {
        if ($this->model_extension_module_peakhour->isRouterExclude($route)) {
            $this->event->register('controller/' . $route . '/after', new Action('extension/module/peakhour/onAfterRenderNoCache'));
        }

        if (!$this->peakhour->pageCachable && !$this->peakhour->urlRule) {
            $pageKey = 'page_' . str_replace('/', '_', $route);
            if (isset($this->peakhour->pages[$pageKey])) {
                $pageSetting = $this->peakhour->pages[$pageKey];
            } else {
                return;
            }

            if ($this->customer->isLogged()) {
                if ($pageSetting['cacheLogin']) {
                    $this->peakhour->pageCachable = true;
                } else {
                    return;
                }
            } else if ($pageSetting['cacheLogout']) {
                $this->peakhour->pageCachable = true;
            } else {
                return;
            }

//            $this->peakhour->cacheTags[] = $pageKey;
        }

        //$this->log('route:' . $route);

        $this->event->unregister('controller/*/before', 'extension/module/peakhour/onAfterInitialize');
        $this->event->register('controller/' . $route . '/after', new Action('extension/module/peakhour/onAfterRender'));

        //$this->log('page cachable:' . $this->peakhour->pageCachable);

//        if ($this->peakhour->esiEnabled) {
//            $esiModules = $this->model_extension_module_peakhour->getESIModules();
//            $route = "";
//            foreach ($esiModules as $key => $module) {
//                if ($module['route'] != $route) {
//                    $route = $module['route'];
//                    $this->event->register('controller/' . $route . '/after', new Action('extension/module/peakhour/onAfterRenderModule'));
//                }
//            }
//            $this->event->register('model/setting/module/getModule', new Action('extension/module/peakhour/onAfterGetModule'));
//        }
    }

    public function onAfterRenderModule($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->pageCachable)) {
            return;
        }

        $esiModules = $this->model_extension_module_peakhour->getESIModules();
        $esiKey = 'esi_' . str_replace('/', '_', $route);
        if (count($args) > 0) {
            $esiKey .= '_' . $args['module_id'];
        }
        if (!isset($esiModules[$esiKey])) {
            return;
        }

        $module = $esiModules[$esiKey];
        $esiType = $module['esi_type'];

        $link = $this->url->link('extension/module/peakhour/renderESI', '');
        $link .= '&esiRoute=' . $route;
        if (isset($module['module']) && ($module['name'] != $module['module'])) {
            $link .= '&module_id=' . $module['module'];
        }

        if ($esiType == 3) {
            $esiBlock = '<esi:include src="' . $link . '" cache-control="public"/>';
        } else if ($esiType == 2) {
            if ($this->emptySession()) {
                return;
            }
            $esiBlock = '<esi:include src="' . $link . '" cache-control="private"/>';
        } else if ($esiType == 1) {
            $esiBlock = '<esi:include src="' . $link . '" cache-control="no-cache"/>';
        } else {
            return;
        }
        $this->peakhour->esiOn = true;

        $output = $this->setESIBlock($output, $route, $esiBlock, '');
    }

    protected function setESIBlock($output, $route, $esiBlock, $divElement)
    {
        if ($route == 'common/header') {
            $bodyElement = stripos($output, '<body');
            if ($bodyElement === false) {
                return $esiBlock;
            }

            return substr($output, 0, $bodyElement) . $esiBlock;
        }

        //for later usage only, currently no demands
        if (!empty($divElement)) {
            
        }

        return $esiBlock;
    }

    protected function getESIBlock($content, $route, $divElement)
    {
        if ($route == 'common/header') {
            $bodyElement = stripos($content, '<body');
            if ($bodyElement === false) {
                return $content;
            }
            return substr($content, $bodyElement);
        }

        //for later usage only, currently no demands
        if (!empty($divElement)) {
            
        }

        return $content;
    }

    public function onAfterRenderNoCache($route, &$args, &$output) {
        $this->checkVary();

        $this->peakhour->lscInstance->noCache();
        $this->log();
    }

    public function onAfterRender($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->pageCachable)) {
            return;
        }

        if (function_exists('http_response_code')) {
            $httpcode = http_response_code();
            if ($httpcode > 201) {
                $this->log("Http Response Code Not Cachable:" . $httpcode);
                return;
            }
        }

        $this->checkVary();

        if (!isset($this->peakhour->setting['module_peakhour_public_ttl'])) {
            $cacheTimeout = 120000;
        } else {
            $cacheTimeout = $this->peakhour->setting['module_peakhour_public_ttl'];
            $cacheTimeout = empty($cacheTimeout) ? 120000 : $cacheTimeout;
        }
        $this->peakhour->lscInstance->setPublicTTL($cacheTimeout);
        $this->peakhour->lscInstance->cachePublic($this->peakhour->cacheTags, $this->peakhour->esiOn);
        $this->log();
    }

    public function renderESI()
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            http_response_code(403);
            return;
        }

        if (isset($this->request->get['action'])) {
            if (($this->peakhour->esiEnabled) && (substr($this->request->get['action'], 0, 4) == 'esi_')) {
                $purgeTag = $this->request->get['action'];
                $this->peakhour->lscInstance->purgePrivate($purgeTag);
                $this->log();
            }

            $this->checkVary();

            $this->response->setOutput($content);
            return;
        }

        if (!isset($this->request->get['esiRoute'])) {
            http_response_code(403);
            return;
        }

        $esiRoute = $this->request->get['esiRoute'];
        $esiKey = 'esi_' . str_replace('/', '_', $esiRoute);
        $module_id = "";
        if (isset($this->request->get['module_id'])) {
            $module_id = $this->request->get['module_id'];
            $esiKey .= '_' . $module_id;
        }
        $this->peakhour->cacheTags[] = $esiKey;

        $this->load->model('extension/module/peakhour');
        $esiModules = $this->model_extension_module_peakhour->getESIModules();
        if (!isset($esiModules[$esiKey])) {
            http_response_code(403);
            return;
        }

        $content = "";
        unset($this->request->get['route']);
        if (empty($module_id)) {
            $content = $this->load->controller($esiRoute);
        } else {
            $setting_info = $this->model_setting_module->getModule($module_id);

            if ($setting_info && $setting_info['status']) {
                $content = $this->load->controller($esiRoute, $setting_info);
            } else {
                http_response_code(403);
                return;
            }
        }

        $content = $this->getESIBlock($content, $esiRoute, '');

        $this->response->setOutput($content);

        $module = $esiModules[$esiKey];
        if ($module['esi_type'] > '1') {
            $cacheTimeout = $module['esi_ttl'];
            $this->peakhour->cacheTags[] = $module['esi_tag'];
            $this->peakhour->lscInstance->setPublicTTL($cacheTimeout);
            if ($module['esi_type'] == '2') {
                $this->peakhour->lscInstance->checkPrivateCookie();
                $this->peakhour->lscInstance->setPrivateTTL($cacheTimeout);
                $this->peakhour->lscInstance->cachePrivate($this->peakhour->cacheTags, $this->peakhour->cacheTags);
            } else {
                $this->peakhour->lscInstance->cachePublic($this->peakhour->cacheTags);
            }
            $this->log();
        }

        $this->event->unregister('controller/*/before', 'extension/module/peakhour/onAfterInitialize');
    }

    public function onAfterGetModule($route, &$args, &$output)
    {
        $output['module_id'] = $args[0];
    }

    // model/account/customer/deleteLoginAttempts/after
    public function onUserAfterLogin($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }
        $this->peakhour->lscInstance->checkPrivateCookie();
        if (!defined('LSC_PRIVATE')) { define('LSC_PRIVATE', true); }
        $this->checkVary();
        if ($this->peakhour->esiEnabled) {
            $this->peakhour->lscInstance->purgeAllPrivate();
            $this->log();
        }
    }

    public function onUserAfterLogout($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        $this->checkVary();
        if ($this->peakhour->esiEnabled) {
            $this->peakhour->lscInstance->purgeAllPrivate();
            $this->log();
        }
    }

    protected function checkVary()
    {
        $vary = array();

        if ($this->session->data['currency'] != $this->config->get('config_currency')) {
            $vary['currency'] = $this->session->data['currency'];
        }

        if ((isset($this->session->data['language'])) && ($this->session->data['language'] != $this->config->get('config_language'))) {
            $vary['language'] = $this->session->data['language'];
        }

        
        //cookie not enabled
        if ((count($vary) == 0) && !$this->checkCookiesEnabled() ){
            return;
        }


        if ($this->customer->isLogged() && isset($this->peakhour->setting['module_peakhour_vary_login']) && ($this->peakhour->setting['module_peakhour_vary_login'] == '1')) {
            $vary['session'] = 'loggedIn';
        }

        if (isset($this->peakhour->setting['module_peakhour_vary_safari']) && ($this->peakhour->setting['module_peakhour_vary_safari'] == '1') && $this->checkSafari()) {
            $vary['browser'] = 'safari';
        }

        if (isset($this->peakhour->setting['module_peakhour_vary_mobile']) && ($this->peakhour->setting['module_peakhour_vary_mobile'] == '1') && ($device = $this->checkMobile())) {
            $vary['device'] = $device;
        }

        if ((count($vary) == 0) && (isset($_COOKIE['lsc_private']) || defined('LSC_PRIVATE'))) {
            $vary['session'] = 'loggedOut';
        }

        ksort($vary);

        $varyKey = $this->implode2($vary, ',', ':');

        $this->peakhour->lscInstance->checkVary($varyKey);
    }

    public function getProducts($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        foreach ($output as $product) {
            $this->peakhour->cacheTags[] = 'P' . $product['product_id'];
        }
//        $this->peakhour->cacheTags[] = 'Product';
    }

    public function getCategories($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

//        $this->log->write($output);

//        $this->peakhour->cacheTags[] = 'Category';
    }

    public function getInformations($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

//        $this->peakhour->cacheTags[] = 'Information';
    }

    public function getManufacturers($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

//        $this->peakhour->cacheTags[] = 'Manufacturer';
    }

    public function getProduct($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        $this->peakhour->cacheTags[] = 'P' . $args[0];
    }

    public function getCategory($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        $this->peakhour->cacheTags[] = 'C' . $args[0];
    }

    public function getInformation($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        $this->peakhour->cacheTags[] = 'I' . $args[0];
    }

    public function getManufacturer($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        $this->peakhour->cacheTags[] = 'M' . $args[0];
    }

    public function editCart($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        if ($this->peakhour->esiEnabled) {
            $this->peakhour->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) { define('LSC_PRIVATE', true); }
            $this->checkVary();
            $purgeTag = 'esi_common_header,esi_cart';
            $this->peakhour->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->checkVary();
        }
    }

    public function confirmOrder($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        $purgeTag = 'Product,Category';
        foreach ($this->cart->getProducts() as $product) {
            $purgeTag .= ',P_' . $product['product_id'];
        }

        if ($this->peakhour->esiEnabled) {
            $purgeTag .= ',esi_cart';
            $this->peakhour->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->peakhour->lscInstance->purgePrivate($purgeTag);
            $this->log();
            $this->checkVary();
        }
    }

    public function emptyCart($route, &$args, &$output) {
        if (($this->peakhour == null) || (!$this->peakhour->pageCachable)) {
            return;
        }
        $this->load->language('common/cart');

        // Totals
        $this->load->model('setting/extension');

        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        $data['text_items'] = sprintf($this->language->get('text_items'), $total,$this->currency->format($total, $this->session->data['currency']));
        $data['products'] = array();
        $data['totals'] = array();
        $data['cart'] = $this->url->link('checkout/cart');
        $data['checkout'] = $this->url->link('checkout/checkout', '', true);

        return $this->load->view('common/cart', $data);
//        return;
    }

    public function addAjax($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->pageCachable)) {
            return;
        }

        $ajax = 'wishlist.add("-1");';
        if ($this->peakhour->esiEnabled && isset($this->peakhour->setting['module_peakhour_ajax_wishlist']) && ($this->peakhour->setting['module_peakhour_ajax_wishlist'] == '0')) {
            $ajax = '';
        }

        if (isset($this->peakhour->setting['module_peakhour_ajax_compare']) && ($this->peakhour->setting['module_peakhour_ajax_compare'] == '1')) {
            $ajax .= 'compare.add("-1");';
        }

        if (!$this->peakhour->esiEnabled || (isset($this->peakhour->setting['module_peakhour_ajax_shopcart']) && ($this->peakhour->setting['module_peakhour_ajax_shopcart'] == '1'))) {
            $output .= '<script type="text/javascript">$(document).ready(function() {try{ ' . $ajax . ' cart.remove("-1");} catch(err){console.log(err.message);}});</script>';
        } else if (!empty($ajax)) {
            $output .= '<script type="text/javascript">$(document).ready(function() { try {  ' . $ajax . ' } catch(err){console.log(err.message);}});</script>';
        }

        $comment = PHP_EOL;
        $output = $comment . $output;
    }

    public function checkWishlist($route, &$args)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader("Access-Control-Allow-Credentials: true");
        $this->response->addHeader("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,POST,PUT");
        $this->response->addHeader("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

        if (isset($this->request->post['product_id']) && ($this->request->post['product_id'] == "-1")) {
            if ($this->customer->isLogged()) {
                $this->load->model('account/wishlist');
                $total = $this->model_account_wishlist->getTotalWishlist();
            } else {
                $total = isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0;
            }
            $this->load->language('account/wishlist');
            $text_wishlist = $this->language->get('text_wishlist');
//            if (!empty($text_wishlist)) {
//                $text_wishlist = 'My Favourites (%s)';
//            }
            $json = array();
            $json['count'] = $total;
            $json['total'] = sprintf($text_wishlist, $total);

            $this->response->setOutput(json_encode($json));
            return json_encode($json);
        }
    }

    public function checkCompare($route, &$args)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader("Access-Control-Allow-Credentials: true");
        $this->response->addHeader("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,POST,PUT");
        $this->response->addHeader("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

        if (isset($this->request->post['product_id']) && ($this->request->post['product_id'] == "-1")) {
            $total = isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0;
            $this->load->language('product/compare');
            $text_compare = $this->language->get('text_compare');
            $json = array();
            if (!empty($text_compare)) {
                $json['total'] = sprintf($text_compare, $total);
            }
            $json['count'] = $total;
            $this->response->setOutput(json_encode($json));
            return json_encode($json);
        }
    }

    public function editWishlist($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        if (($this->peakhour->esiEnabled) && isset($this->peakhour->setting['module_peakhour_ajax_wishlist']) && ($this->peakhour->setting['module_peakhour_ajax_wishlist'] == '1')) {
            $this->peakhour->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) { define('LSC_PRIVATE', true);}
            $this->checkVary();
            $purgeTag = 'esi_common_header,esi_wishlist';
            $this->peakhour->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->checkVary();
        }
    }

    public function editCompare($route, &$args, &$output)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        if (($this->peakhour->esiEnabled) && isset($this->peakhour->setting['module_peakhour_ajax_compare']) && ($this->peakhour->setting['module_peakhour_ajax_compare'] == '1')) {
            $this->peakhour->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) { define('LSC_PRIVATE', true); }
            $this->checkVary();
            $purgeTag = 'esi_common_header,esi_compare';
            $this->peakhour->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->checkVary();
        }
    }

    public function editCurrency($route, &$args)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        if ($this->peakhour->esiEnabled) {
            $this->peakhour->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) { define('LSC_PRIVATE', true); }
        }
        $this->session->data['currency'] = $this->request->post['code'];
        $this->checkVary();
    }

    public function editLanguage($route, &$args)
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            return;
        }

        if ($this->peakhour->esiEnabled) {
            $this->peakhour->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) { define('LSC_PRIVATE', true); }
        }

        $this->session->data['language'] = $this->request->post['code'];
        $this->checkVary();
    }

    public function log($content = null, $logLevel = self::LOG_INFO)
    {
        if ($this->peakhour == null) {
            $this->load->model('extension/module/peakhour');
            $this->peakhour = (object) array('setting' => $this->model_extension_module_peakhour->getItems());
        }

        if ($content == null) {
            if (!$this->peakhour->lscInstance) {
                return;
            }
            $content = $this->peakhour->lscInstance->getLogBuffer();
        }

        if (!isset($this->peakhour->setting['module_peakhour_log_level'])) {
            return;
        }

        $logLevelSetting = $this->peakhour->setting['module_peakhour_log_level'];

        if (isset($this->session->data['peakhourOption']) && ($this->session->data['peakhourOption'] == "debug")) {
            $this->log->write($content);
            return;
        } else if ($logLevelSetting == self::LOG_DEBUG) {
            return;
        } else if ($logLevel > $logLevelSetting) {
            return;
        }

        $logInfo = "Peakhour Cache Info:\n";
        if ($logLevel == self::LOG_ERROR) {
            $logInfo = "Peakhour Cache Error:\n";
        } else if ($logLevel == self::LOG_DEBUG) {
            $logInfo = "Peakhour Cache Debug:\n";
        }

        $this->log->write($logInfo . $content);
    }

    public function purgeAll()
    {
        $cli = false;

        if (php_sapi_name() == 'cli') {
            $cli = true;
        }

        if (isset($this->request->get['from']) && ($this->request->get['from'] == 'cli')) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $serverIP = $_SERVER['SERVER_ADDR'];
            if ((substr($serverIP, 0, 7) == "127.0.0") || (substr($ip, 0, 7) == "127.0.0") || ($ip == $serverIP)) {
                $cli = true;
            }
        }

        if (!$cli) {
            http_response_code(403);
            return;
        }

        $url = $this->url->link('extension/module/peakhour/purgeAllAction');
        $content = $this->file_get_contents_curl($url);
        echo $content;
    }

    public function purgeAllAction()
    {
        if (($this->peakhour == null) || (!$this->peakhour->cacheEnabled)) {
            http_response_code(403);
            return;
        }

        $visitorIP = $_SERVER['REMOTE_ADDR'];
        $serverIP = $_SERVER['SERVER_ADDR'];

        if (($visitorIP == "127.0.0.1") || ($serverIP == "127.0.0.1") || ($visitorIP == $serverIP)) {
//            $lscInstance = new PeakhourCore();
//            $lscInstance->purgeAllPublic();
            echo 'All Peakhour Cache has been purged' . PHP_EOL;
            flush();
        } else {
            echo 'Operation not allowed from this device' . PHP_EOL;
            flush();
            http_response_code(403);
        }
    }

    private function microtimeMinus($start, $end)
    {
        list($s_usec, $s_sec) = explode(" ", $start);
        list($e_usec, $e_sec) = explode(" ", $end);
        $diff = ((int) $e_sec - (int) $s_sec) * 1000000 + ((float) $e_usec - (float) $s_usec) * 1000000;
        return $diff;
    }

    protected function emptySession()
    {
        if (isset($_COOKIE['lsc_private'])) {
            return false;
        }

        if ($this->customer->isLogged()) {
            return false;
        }

        if ($this->session->data['currency'] != $this->config->get('config_currency')) {
            return false;
        }

        if ($this->session->data['language'] != $this->config->get('config_language')) {
            return false;
        }

        return true;
    }

    protected function implode2(array $arr, $d1, $d2)
    {
        $arr1 = array();

        foreach ($arr as $key => $val) {
            $arr1[] = urlencode($key) . $d2 . urlencode($val);
        }
        return implode($d1, $arr1);
    }

    protected function file_get_contents_curl($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    protected function checkMobile()
    {
        if (defined('JOURNAL3_ACTIVE')) {
            //error_log(print_r('Journal3 mobile detection algorithm used',true));
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== FALSE) {
                return 'mobile';
            } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== FALSE) {
                return 'tablet';
            } elseif ((strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== FALSE) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== FALSE)) {
                return 'mobile';
            } elseif ((strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== FALSE) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') == FALSE)) {
                return 'tablet';
            } else {
                return false;
            }
        } else {
            //only use .htaccess rule to mark separate cache copy for mobile view
            return false;
//            include_once(DIR_SYSTEM . 'library/Mobile_Detect/Mobile_Detect.php');
//            $detect = new Mobile_Detect();
//            if ($detect->isTablet()) {
//                return 'tablet';
//            } else if ($detect->isMobile()) {
//                return 'mobile';
//            } else {
//                return false;
//            }
        }
    }

    protected function checkSafari()
    {

        if (strpos($_SERVER['HTTP_USER_AGENT'], 'CriOS') !== FALSE) {
            return FALSE;
        }

        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) {
            return FALSE;
        }
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== FALSE) {
            return TRUE;
        }
        return FALSE;
    }

    protected function checkCookiesEnabled()
    {
        if (isset($_SERVER['HTTP_COOKIE'])) {
            return TRUE;
        }
        return FALSE;
    }
    

    protected function CountNumberOfPages($filter_data) {

        if (isset($this->request->get['limit'])) {
            $limit = (int) $this->request->get['limit'];
        } else if (defined('JOURNAL3_ACTIVE')) {
            $limit = $this->journal3->themeConfig('product_limit');
        } else {
            return 1;
        }

        if (defined('JOURNAL3_ACTIVE')) {
            $this->load->model('journal3/filter');

            $filter_data = array_merge($this->model_journal3_filter->parseFilterData(), $filter_data);

            $this->model_journal3_filter->setFilterData($filter_data);

            \Journal3\Utils\Profiler::start('journal3/filter/total_products');

            $product_total = $this->model_journal3_filter->getTotalProducts();

            \Journal3\Utils\Profiler::end('journal3/filter/total_products');
        } else {
            $product_total = $this->model_catalog_product->getTotalProducts($filter_data);
        }

        $num_pages = ceil($product_total / $limit);

        return $num_pages;
    }
    

}
