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

use GuzzleHttp\Client;


class PeakhourCore extends PeakhourBase
{

    const FLUSH_ALL = 'all';

    protected $site_only_tag = '';
    protected $peakhourUrl;
    protected $apiKey;
    protected $domain;
    protected $log;

    /**
     *
     *  set the specified tag for this site
     *
     * @since   1.0.0
     */
    public function __construct($setting, $log)
    {
        $this->log = $log;

        if (isset($setting['module_peakhour_peakhour_url'])) {
            $this->peakhourUrl = $setting['module_peakhour_peakhour_url'];
        }
        if (isset($setting['module_peakhour_peakhour_domain'])) {
            $this->domain = $setting['module_peakhour_peakhour_domain'];
        }
        if (isset($setting['module_peakhour_api_key'])) {
            $this->apiKey = $setting['module_peakhour_api_key'];
        }
    }

    /**
     *
     * put tag into Array in the format for this site only.
     *
     * @since   1.0.0
     */
    protected function tagsForSite(Array &$tagArray, $rawTags, $prefix = "")
    {
        if (!isset($rawTags)) {
            return;
        }

        if (empty($rawTags)) {
            return;
        }

        if(is_array($rawTags)){
            $tags = $rawTags;
        } else {
            $tags = explode(",", $rawTags);
        }
        
        foreach ($tags as $tag) {
            if(trim($tag)==""){
                continue;
            }
            $tagStr = $prefix . $this->site_only_tag . trim($tag);
            if(!in_array($tagStr, $tagArray, false)){
                array_push($tagArray, $tagStr);
            }
        }
    }

    /**
     *
     *  purge all public cache of this site
     *
     * @since   1.0.0
     */
    public function purgeAllPublic()
    {
        $resourcesUrl = $this->getResourcesUrl();
        $body = $this->makeRequestBody([], self::FLUSH_ALL, true);
        return $this->doRequest($resourcesUrl, 'DELETE', $body);
    }

    /**
     *
     *  purge all private cache of this session
     *
     * @since   0.1
     */
    public function purgeAllPrivate()
    {
        $LSheader = self::CACHE_PURGE . 'private,' . $this->site_only_tag;
        $this->cacheTagHeader($LSheader);
    }

    /**
     *
     * Cache this page for public use if not cached before
     *
     * @since   1.0.1
     */
    public function cachePublic($publicTags, $esi=false)
    {
        $siteTags = Array();
        $this->tagsForSite($siteTags, $publicTags);
        if(count($siteTags)<=0){
            return;
        }
        
        $LSheader = self::PUBLIC_CACHE_CONTROL . $this->public_cache_timeout;
        if($esi){
            $LSheader .= ',esi=on';
        }        
        $this->cacheTagHeader($LSheader);

        array_push($siteTags, $this->site_only_tag);
        $LSheader = self::CACHE_TAG . implode(',', $siteTags);
        $this->cacheTagHeader($LSheader);
    }

    public function noCache() {
        $LSheader = self::PUBLIC_CACHE_CONTROL . '0';
        $this->cacheTagHeader($LSheader);
    }

    /**
     *
     * Cache this page for private session if not cached before
     *
     * @since   0.1
     */
    public function cachePrivate($publicTags, $privateTags = "", $esi=false)
    {
        $LSheader = self::PRIVATE_CACHE_CONTROL . $this->private_cache_timeout;
        if($esi){
            $LSheader .= ',esi=on';
        }
        $this->cacheTagHeader($LSheader);

        $siteTags = Array();
        $this->tagsForSite($siteTags, $publicTags, "public:");
        if(count($siteTags)>0){
            array_push($siteTags, "public:" . $this->site_only_tag);
        }
        
        $this->tagsForSite($siteTags, $privateTags);
        array_push($siteTags, $this->site_only_tag);
        
        $LSheader = self::CACHE_TAG . implode(',', $siteTags);
        $this->cacheTagHeader($LSheader);
        
    }

    public function getSiteOnlyTag(){
        return $this->site_only_tag;
    }

    public function purgeTags($tags)
    {
        if (empty($tags)) {
            return array('success' => true, 'error' => null, 'body' => '');
        }
        return $this->doRequest($this->getTagsUrl(),'DELETE', json_encode(array('tags' => $tags)));
    }

    function purgeUrls($urls)
    {
        $resourcesUrl = $this->getResourcesUrl();
        $body = $this->makeRequestBody($urls);
        return $this->doRequest($resourcesUrl, 'DELETE', $body);
    }


    public function testConnection($apiKey, $domain)
    {
        $resourcesUrl = $this->getResourcesUrl($domain);
        return $this->doRequest($resourcesUrl, 'OPTIONS', null);
    }

    function makeRequestBody($urls, $flushType=self::FLUSH_ALL, $purgeAll=false)
    {
        $paths = array();

        foreach ($urls as $url) {
            $parsed = parse_url( $url );
            $path = ( isset( $parsed['path'] ) ? $parsed['path'] : null );
            if ( empty( $path ) ) {
                $path = '/';
            }
            if ( isset( $parsed['query'] ) ) {
                $path = $path . '?' . $parsed['query'];
            }
            array_push($paths, $path );

        }
        $paths = array_values(array_unique($paths));

        return json_encode(array('paths' => $paths, 'flush_type' => $flushType, 'purge_all' => $purgeAll));
    }

    function getTagsUrl()
    {
        if (is_null($this->domain) || is_null($this->peakhourUrl)) {
            return "";
        }
        return $this->peakhourUrl . 'domains/' . $this->domain . '/services/rp/cdn/tag';
    }

    function getResourcesUrl($domain=null)
    {
        if (is_null($this->domain) || is_null($this->peakhourUrl)) {
            return "";
        }
        return $this->peakhourUrl . 'domains/' . $this->domain . '/services/rp/cdn/resources';
    }

    function doRequest($url, $method, $body=null)
    {
        if (is_null($this->apiKey) || is_null($this->domain) || is_null($this->peakhourUrl)) {
            return array('success' => false, 'error' => "You need to make sure you've provided your API Key, peakhour api url, and domain in the Peakhour module settings");
        }
        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        );

        try {

            $client = new Client();

            if (!is_null($body)) {
                $request = $client->createRequest($method, $url, ['headers' => $headers, 'body' => $body]);
            }
            else {
                $request = $client->createRequest($method, $url, ['headers' => $headers]);
            }
            $response = $client->send($request);

            $this->log->write("returning");
            return array('success' => true, 'error' => null, 'body' => $response->getBody());
        } catch (\Exception $e) {
            $this->log->write("Failed $method to $url: " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
}
