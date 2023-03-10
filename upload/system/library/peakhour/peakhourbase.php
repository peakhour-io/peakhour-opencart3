<?php

/**
 * @since      1.0.0
 * @author     Peakhour.io Pty Ltd <support@peakhour.io>
 * @copyright  Copyright (c) 2021 Peakhour.io Pty Ltd, (https://www.peakhour.io)
 * @license    https://opensource.org/licenses/GPL-3.0
 *
 * The following code is a derivative work of the code from the LiteSpeed LSCache project,
 * which is licensed GPLv3. This code therefore is also licensed under the terms
 * of the GNU Public License, verison 3.
 */
class PeakhourBase
{
    const PUBLIC_CACHE_CONTROL = 'Peakhour-Cdn-Cache-Control:public,max-age=';
    const PRIVATE_CACHE_CONTROL = 'Cache-Control:private,max-age=';
    const CACHE_PURGE = '';
    const CACHE_TAG = 'X-OpenCart-Tag:';
    const VARY_COOKIE = '_phcache_vary';
    const PRIVATE_COOKIE = 'phc_private';

    protected $public_cache_timeout = '1200000';
    protected $private_cache_timeout = '7200';
    protected $logbuffer = "";
    protected $headerCallback;

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
            
            $tagStr = $prefix . trim($tag);
            if(!in_array($tagStr, $tagArray, false)){
                array_push($tagArray, $tagStr);
            }
        }
    }

    
    /**
     *
     *  purge private cache with specified tags for this site.
     *
     * @since   1.0.0
     */
    public function purgePrivate($privateTags)
    {
        if ((!isset($privateTags)) || ($privateTags == "")) {
            return;
        }

        $siteTags = Array();
        $this->tagsForSite($siteTags, $privateTags);
        $LSheader = self::CACHE_PURGE . 'private,' . implode(',', $siteTags) ;
        $this->cacheTagHeader($LSheader);
    }

    /**
     *
     *  purge all public cache of this site
     *
     * @since   1.0.0
     */
    public function purgeAllPublic()
    {
        $LSheader = self::CACHE_PURGE . 'public,*';
        $this->cacheTagHeader($LSheader);
    }

    /**
     *
     *  purge all private cache of this session
     *
     * @since   0.1
     */
    public function purgeAllPrivate()
    {
        $LSheader = self::CACHE_PURGE . 'private,*';
        $this->cacheTagHeader($LSheader);
    }

    /**
     *
     * Cache this page for public use if not cached before
     *
     * @since   1.0.0
     * @param string $tags
     */
    public function cachePublic($publicTags, $esi=false)
    {
        if (!isset($publicTags) || ($publicTags == null)) {
            return;
        }

        $LSheader = self::PUBLIC_CACHE_CONTROL . $this->public_cache_timeout;
        if($esi){
            $LSheader .= ',esi=on';
        }
        
        $this->cacheTagHeader($LSheader);

        $siteTags = Array();
        $this->tagsForSite($siteTags, $publicTags);

        $LSheader =  self::CACHE_TAG . implode(',', $siteTags) ;
        $this->cacheTagHeader($LSheader);
    }

    /**
     *
     * Cache this page for private session if not cached before
     *
     * @since   0.1
     */
    public function cachePrivate($publicTags, $privateTags = "pvt", $esi = false)
    {


        $siteTags = Array();
        $this->tagsForSite($siteTags, $publicTags, "public:");
        $this->tagsForSite($siteTags, $privateTags);
        
        if(count($siteTags)<=0){
            return;
        }

        $LSheader = self::PRIVATE_CACHE_CONTROL . $this->private_cache_timeout;
        if($esi){
            $LSheader .= ',esi=on';
        }
        $this->cacheTagHeader($LSheader);
        
        $LSheader =  self::CACHE_TAG . implode(',', $siteTags) ;
        $this->cacheTagHeader($LSheader);
    }

    /**
     *
     * Cache this page for private use if not cached before
     *
     * @since   1.0.0
     */
    protected function cacheTagHeader($LSheader)
    {
        $this->logbuffer .= $LSheader . "\n";
        if(empty($this->headerCallback)){
            header($LSheader);
        }
        else{
            call_user_func_array($this->headerCallback, array($LSheader));
        }
    }

    /**
     *
     *  set or delete private cookie.
     *
     * @since   1.0.0
     */
    public function checkPrivateCookie($path = '/')
    {
        if (!isset($_COOKIE[self::PRIVATE_COOKIE])) {
            setcookie(self::PRIVATE_COOKIE, md5((String)rand()), 0, $path);
        }
    }

    /**
     *
     *  set or delete cache vary cookie, if cookie need no change return true;
     *
     * @since   1.0.0
     */
    public function checkVary($value, $path='/')
    {
        if ($value == "") {
            if (isset($_COOKIE[self::VARY_COOKIE])) {
                setcookie(self::VARY_COOKIE, "", '0', $path);
                return false;
            }
            return true;
        }
        
        if(!isset($_COOKIE[self::VARY_COOKIE])){
            setcookie(self::VARY_COOKIE, $value, '0', $path);
            return false;
        }

        if($_COOKIE[self::VARY_COOKIE] != $value){
            setcookie(self::VARY_COOKIE, $value, '0', $path);
            return false;
        }
        
        return true;
    }

    /**
     *
     *  get LiteSpeedCache special head log
     *
     * @since   1.0.0
     */
    public function getLogBuffer()
    {
        $retVal = $this->logbuffer;
        $this->logbuffer = '';
        return $retVal;
    }
    
    
    public function setHeaderFunction($class, $method){
        $callable = array($class, $method);
	
        if (is_callable($callable)) {
            $this->headerCallback = $callable;
        }
    }

    public function setPublicTTL($publicTTL){
        $this->public_cache_timeout = $publicTTL;
    }

    public function setPrivateTTL($privateTTL){
        $this->private_cache_timeout = $privateTTL;
    }


    
}
