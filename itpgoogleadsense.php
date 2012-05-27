<?php
/**
 * @package		 ITPGoogleAdSense Plugins
 * @subpackage	 Advertisement
 * @copyright    Copyright (C) 2010 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * ITPShare is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

/**
 * ITPGoogleAdSense Plugin
 *
 * @package		ITPrism Plugins
 * @subpackage	Advertisement
 * @since 		1.5
 */
class plgContentITPGoogleAdSense extends JPlugin {
    
    private $currentView    = "";
    private $currentTask    = "";
    private $currentOption  = "";
    
    public function __construct($subject, $params){
        parent::__construct($subject, $params);
        
        $app = JFactory::getApplication();
        /* @var $app JApplication */

        if($app->isAdmin()) {
            return;
        }
      
        $this->currentView    = JRequest::getCmd("view");
        $this->currentTask    = JRequest::getCmd("task");
        $this->currentOption  = JRequest::getCmd("option");
        
    }
    
    /**
     * Adds Google AdSense into articles
     *
     * Method is called by the view
     *
     * @param   string  The context of the content being passed to the plugin.
     * @param   object  The content object.  Note $article->text is also available
     * @param   object  The content params
     * @param   int     The 'page' number
     * @since   1.6
     */
    public function onContentPrepare($context, &$article, &$params, $limitstart){
        
        if (!$article OR !isset($this->params)) { return; };            
        
        $app =& JFactory::getApplication();
        /** @var $app JApplication **/

        if($app->isAdmin()) {
            return;
        }
        
        $doc     = JFactory::getDocument();
        /**  @var $doc JDocumentHtml **/
        $docType = $doc->getType();
        
        // Check document type
        if(strcmp("html", $docType) != 0){
            return;
        }
       
        if($this->isRestricted($article, $context)) {
        	return;
        }
        
        // Generate content
		$content      = $this->getContent($article, $context);
        $position     = $this->params->get('position');
        
        switch($position){
            case 1:
                $article->text = $content . $article->text;
                break;
            case 2:
                $article->text = $article->text . $content;
                break;
            default:
                $article->text = $content . $article->text . $content;
                break;
        }
        
        return true;
    }
    
    private function isRestricted($article, $context) {
    	
    	$result = false;
    	
    	switch($this->currentOption) {
            case "com_content":
            	
        		if($this->isContentRestricted($article, $context)) {
                    $result = true;
                }
                break;
	                
            default:
                $result = true;
                break;   
        }
        
        return $result;
        
    }
    
	/**
     * 
     * Checks allowed articles, exluded categories/articles,... for component COM_CONTENT
     * @param object $article
     * @param string $context
     */
    private function isContentRestricted(&$article, $context) {
        
        // Check for currect context
        if(strpos($context, "com_content") === false) {
           return true;
        }
        
    	/** Check for selected views, which will display the buttons. **/   
        /** If there is a specific set and do not match, return an empty string.**/
        $showInArticles     = $this->params->get('showInArticles');
        if(!$showInArticles AND (strcmp("article", $this->currentView) == 0)){
            return true;
        }
        
        $excludeArticles = $this->params->get('excludeArticles');
        if(!empty($excludeArticles)){
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        JArrayHelper::toInteger($excludeArticles);
        
        // Exluded categories
        $excludedCats           = $this->params->get('excludeCats');
        if(!empty($excludedCats)){
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        JArrayHelper::toInteger($excludedCats);
        
        // Included Articles
        $includedArticles = $this->params->get('includeArticles');
        if(!empty($includedArticles)){
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        JArrayHelper::toInteger($includedArticles);
        
        if(!in_array($article->id, $includedArticles)) {
            // Check exluded articles
            if(in_array($article->id, $excludeArticles) OR in_array($article->catid, $excludedCats)){
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate content
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @return  string      Returns html code or empty string.
     */
    private function getContent(&$article, $context){
        
        $ips = explode(",",$this->params->get('blockedIPs'));
        foreach($ips as &$ip) {
            $ip = trim($ip);
        }
        
        $removeAddress = JArrayHelper::getValue($_SERVER, "REMOTE_ADDR");
        if(in_array($removeAddress,$ips)) {
            return '<div style="clear:both;">' . $this->params->get('altMessage') . '</div>';
        }
        
        /* Let's show the ad */
        $css            = $this->params->get('css');
        $publisherId    = $this->params->get('publisherId');
        $slotId         = $this->params->get('slot');
        $channelId      = $this->params->get('channel');
        
        // Get size
        $adFormat   = $this->params->get('format');
        $format     = explode("-", $adFormat);
        $width      = explode("x", $format[0]);
        $height     = explode("_", $width[1]);
        
        $jmAdCss    = $this->params->get('joomlaspan_ad_css');
        
        return  '<div class="' . $css . '"><script type="text/javascript"><!--
google_ad_client = "' . $publisherId . '";
google_ad_slot = "' . $slotId . '";
google_ad_width = ' . $width[0] . ';
google_ad_height = ' . $height[0] . ';
//-->
</script>
<script type="text/javascript"
src="//pagead2.googlesyndication.com/pagead/show_ads.js">
</script></div>';

    }
    
}